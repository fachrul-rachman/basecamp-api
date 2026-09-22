<?php

namespace App\Services\Scheduling;

use App\Models\DepartmentRequest;
use App\Models\Holiday;
use App\Models\Task;
use App\Models\TaskChecklist;
use App\Models\WorkItem;
use App\Notifications\WorkItemEvent;
use App\Services\ChecklistAssignmentService;
use App\Services\NotificationDispatcher;
use App\Support\Scheduling\ScheduleType;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Generates concrete Work Items H-1 (one day before the operational date)
 * from active Task Checklists, per docs/02-BUSINESS-RULES.md §1-2.
 *
 * `event`-type checklists are intentionally NOT generated here — they have
 * no fixed schedule to generate from; a Work Item for them is created when
 * a PIC becomes assignable (Department Request assignment), which is a
 * Phase 6 concern ("assignment/reassignment history"), not H-1 batch
 * generation.
 */
class WorkItemGenerator
{
    public function __construct(
        private OperationalWindowResolver $windowResolver,
        private NotificationDispatcher $notifications,
        private ChecklistAssignmentService $assignments,
    ) {}

    /**
     * Idempotent — safe to re-run for the same date (see
     * docs/09-IMPLEMENTATION-PLAN.md Phase 5 acceptance).
     */
    public function generateForDate(CarbonInterface $date): int
    {
        $count = 0;

        TaskChecklist::query()
            ->where('is_active', true)
            ->whereHas('task', fn ($q) => $q->where('status', Task::STATUS_ACTIVE))
            ->with(['task', 'departmentRequests', 'assigneeOverrides'])
            ->chunkById(100, function ($checklists) use ($date, &$count) {
                foreach ($checklists as $checklist) {
                    $count += $this->generateForChecklist($checklist, $date);
                }
            });

        return $count;
    }

    /**
     * Creates the Work Item for an `event`-type checklist immediately once
     * a PIC is assignable — on Department Request assignment (cross
     * department) or checklist creation (same department). There is no
     * fixed schedule to generate from, so this never runs from the H-1
     * batch job (see docs/05-DATABASE-SCHEMA.md §20).
     */
    public function generateForEvent(TaskChecklist $checklist, ?string $assigneeId, ?string $departmentRequestId): WorkItem
    {
        $hours = (int) ($checklist->schedule_config['response_window_hours'] ?? 24);
        $deadline = now()->addHours($hours);

        $item = WorkItem::create([
            'task_id' => $checklist->task_id,
            'task_checklist_id' => $checklist->id,
            'department_request_id' => $departmentRequestId,
            'responsible_department_id' => $checklist->target_department_id,
            'assignee_id' => $assigneeId,
            'operational_date' => null,
            'available_at' => now(),
            'deadline_at' => $deadline,
            'failure_at' => $deadline,
            'execution_status' => WorkItem::EXECUTION_PENDING,
            'compliance_status' => WorkItem::COMPLIANCE_PENDING,
            'required_evidence_count' => $checklist->evidence_min_count,
            'rule_snapshot' => $this->snapshot($checklist),
        ]);

        $this->notifyAssignee($item);

        return $item;
    }

    private function generateForChecklist(TaskChecklist $checklist, CarbonInterface $date): int
    {
        $type = ScheduleType::from($checklist->schedule_type);

        if ($type === ScheduleType::Event) {
            return 0;
        }

        [$assigneeId, $departmentRequestId, $eligible] = $this->resolveResponsibility($checklist, $date);

        if (! $eligible) {
            return 0;
        }

        return match ($type) {
            ScheduleType::Daily => $this->generateDateBased($checklist, $date, $assigneeId, $departmentRequestId, fn () => true),
            ScheduleType::Weekly => $this->generateDateBased(
                $checklist, $date, $assigneeId, $departmentRequestId,
                fn () => in_array((int) $date->dayOfWeek, $checklist->schedule_config['weekdays'] ?? [], true)
            ),
            ScheduleType::Monthly => $this->generateDateBased(
                $checklist, $date, $assigneeId, $departmentRequestId,
                fn () => (int) $date->day === (int) ($checklist->schedule_config['day_of_month'] ?? -1)
            ),
            ScheduleType::OneTime => $this->generateOneTime($checklist, $date, $assigneeId, $departmentRequestId),
            ScheduleType::WeeklyQuota => $this->generateQuotaPeriod($checklist, $date, $assigneeId, $departmentRequestId),
            default => 0,
        };
    }

    /**
     * @return array{0: ?string, 1: ?string, 2: bool} [assigneeId, departmentRequestId, eligible]
     */
    private function resolveResponsibility(TaskChecklist $checklist, CarbonInterface $date): array
    {
        $request = $checklist->departmentRequests->first();

        if (! $request) {
            if ($checklist->schedule_type === ScheduleType::WeeklyQuota->value) {
                return [null, null, true];
            }

            return [$this->assignments->resolveEffectiveAssignee($checklist, $date), null, true];
        }

        // Cross-department work only generates once the target department
        // has actually accepted responsibility (see docs/02-BUSINESS-RULES.md
        // §12: unresolved requests are the target Manager's own
        // accountability item, not silently-generated unassigned work).
        if ($request->status !== DepartmentRequest::STATUS_ASSIGNED) {
            return [null, null, false];
        }

        return [$request->assigned_pic_id, $request->id, true];
    }

    private function generateDateBased(
        TaskChecklist $checklist,
        CarbonInterface $date,
        ?string $assigneeId,
        ?string $departmentRequestId,
        callable $matches
    ): int {
        if (! $matches()) {
            return 0;
        }

        if (! $this->withinTaskRange($checklist->task, $date)) {
            return 0;
        }

        if (! $checklist->works_on_holidays && $this->isHoliday($date)) {
            return 0;
        }

        return $this->createIfMissing($checklist, $date, $assigneeId, $departmentRequestId) ? 1 : 0;
    }

    private function generateOneTime(TaskChecklist $checklist, CarbonInterface $date, ?string $assigneeId, ?string $departmentRequestId): int
    {
        $start = Carbon::parse($checklist->schedule_config['date']);
        $end = Carbon::parse($checklist->schedule_config['end_date'] ?? $checklist->schedule_config['date']);

        if ($date->toDateString() < $start->toDateString() || $date->toDateString() > $end->toDateString()) {
            return 0;
        }

        if (! $checklist->works_on_holidays && $this->isHoliday($date)) {
            return 0;
        }

        return $this->createIfMissing($checklist, $date, $assigneeId, $departmentRequestId) ? 1 : 0;
    }

    private function generateQuotaPeriod(TaskChecklist $checklist, CarbonInterface $date, ?string $assigneeId, ?string $departmentRequestId): int
    {
        // Same H-1 preview timing as date-based types: generate the whole
        // period's slots the day before the period starts. Bounds are
        // computed from $date+1 (not $date), otherwise a Sunday's "start
        // of week" resolves backward to last Monday instead of forward to
        // the upcoming one.
        $nextDay = $date->copy()->addDay()->startOfDay();
        $period = $checklist->schedule_config['period'] ?? 'week';
        [$periodStart, $periodEnd] = $this->periodBounds($nextDay, $period);

        if (! $nextDay->isSameDay($periodStart)) {
            return 0;
        }

        if (! $this->withinTaskRange($checklist->task, $periodStart)) {
            return 0;
        }

        $targetCount = (int) ($checklist->schedule_config['target_count'] ?? 1);

        return DB::transaction(function () use ($checklist, $periodStart, $periodEnd, $targetCount, $assigneeId, $departmentRequestId) {
            // No DB-level unique constraint covers quota slots (period_start/
            // period_end are intentionally shared by up to target_count
            // rows), so the count-then-create below is guarded by locking
            // the checklist row instead — serializes concurrent generation
            // runs for this checklist so they can't race past target_count.
            TaskChecklist::query()->whereKey($checklist->id)->lockForUpdate()->first();

            $existing = WorkItem::query()
                ->where('task_checklist_id', $checklist->id)
                ->where('period_start', $periodStart)
                ->where('period_end', $periodEnd)
                ->count();

            $toCreate = max(0, $targetCount - $existing);

            for ($i = 0; $i < $toCreate; $i++) {
                $item = WorkItem::create([
                    'task_id' => $checklist->task_id,
                    'task_checklist_id' => $checklist->id,
                    'department_request_id' => $departmentRequestId,
                    'responsible_department_id' => $checklist->target_department_id,
                    'assignee_id' => $assigneeId,
                    'operational_date' => null,
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'available_at' => $periodStart,
                    'deadline_at' => $periodEnd,
                    'failure_at' => $periodEnd,
                    'execution_status' => WorkItem::EXECUTION_PENDING,
                    'compliance_status' => WorkItem::COMPLIANCE_PENDING,
                    'required_evidence_count' => $checklist->evidence_min_count,
                    'rule_snapshot' => $this->snapshot($checklist),
                ]);

                $this->notifyAssignee($item);
            }

            return $toCreate;
        });
    }

    private function createIfMissing(TaskChecklist $checklist, CarbonInterface $date, ?string $assigneeId, ?string $departmentRequestId): bool
    {
        $exists = WorkItem::query()
            ->where('task_checklist_id', $checklist->id)
            ->whereDate('operational_date', $date->toDateString())
            ->exists();

        if ($exists) {
            return false;
        }

        $window = $this->windowResolver->resolve(
            $date,
            $checklist->schedule_config['start_time'],
            $checklist->schedule_config['end_time']
        );

        // The generator now runs every 15 minutes and checks today as
        // well as tomorrow (not just a single nightly run for tomorrow),
        // so a Checklist created partway through today could otherwise
        // get a Work Item whose deadline has already passed at the
        // moment of creation — the next evaluation run would then mark
        // it late and open a real Finding against a PIC who never had a
        // chance to act on it. Skip creating it entirely in that case;
        // a window still open (even partially) still generates normally.
        if ($window['deadline_at']->isPast()) {
            return false;
        }

        $item = WorkItem::create([
            'task_id' => $checklist->task_id,
            'task_checklist_id' => $checklist->id,
            'department_request_id' => $departmentRequestId,
            'responsible_department_id' => $checklist->target_department_id,
            'assignee_id' => $assigneeId,
            'operational_date' => $date->toDateString(),
            'available_at' => $window['available_at'],
            'deadline_at' => $window['deadline_at'],
            'failure_at' => $window['failure_at'],
            'execution_status' => WorkItem::EXECUTION_PENDING,
            'compliance_status' => WorkItem::COMPLIANCE_PENDING,
            'required_evidence_count' => $checklist->evidence_min_count,
            'rule_snapshot' => $this->snapshot($checklist),
        ]);

        $this->notifyAssignee($item);

        return true;
    }

    private function notifyAssignee(WorkItem $item): void
    {
        if ($item->assignee_id) {
            $this->notifications->notifyUser($item->assignee, new WorkItemEvent($item, WorkItemEvent::ASSIGNED));
        }
    }

    private function withinTaskRange(Task $task, CarbonInterface $date): bool
    {
        if ($date->toDateString() < $task->starts_at->toDateString()) {
            return false;
        }

        if ($task->ends_at && $date->toDateString() > $task->ends_at->toDateString()) {
            return false;
        }

        return true;
    }

    private function isHoliday(CarbonInterface $date): bool
    {
        return Holiday::query()->whereDate('date', $date->toDateString())->exists();
    }

    /**
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    private function periodBounds(CarbonInterface $date, string $period): array
    {
        if ($period === 'month') {
            return [
                $date->copy()->startOfMonth()->startOfDay(),
                $date->copy()->endOfMonth()->endOfDay(),
            ];
        }

        return [
            $date->copy()->startOfWeek(CarbonInterface::MONDAY)->startOfDay(),
            $date->copy()->endOfWeek(CarbonInterface::SUNDAY)->endOfDay(),
        ];
    }

    /**
     * Snapshot of effective rules at generation time, so later edits to
     * the checklist don't retroactively change already-generated history
     * (see docs/02-BUSINESS-RULES.md §1).
     */
    private function snapshot(TaskChecklist $checklist): array
    {
        return [
            'title' => $checklist->title,
            'instructions' => $checklist->instructions,
            'schedule_type' => $checklist->schedule_type,
            'schedule_config' => $checklist->schedule_config,
            'evidence_min_count' => $checklist->evidence_min_count,
            'allow_upload' => $checklist->allow_upload,
            'allow_camera' => $checklist->allow_camera,
            'works_on_holidays' => $checklist->works_on_holidays,
        ];
    }
}
