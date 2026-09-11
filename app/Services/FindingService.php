<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Finding;
use App\Models\Role;
use App\Models\SlaInstance;
use App\Models\User;
use App\Models\WorkItem;
use App\Notifications\FindingEvent;
use App\Services\Scheduling\WorkingTimeCalculator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Creates automatic Findings (late/failed Work Items) and starts the
 * Manager/ISO SLA clock for them (docs/02-BUSINESS-RULES.md §16, §8, §9).
 */
class FindingService
{
    public function __construct(
        private SlaSettingService $slaSettings,
        private WorkingTimeCalculator $calculator,
        private NotificationDispatcher $notifications,
    ) {}

    /**
     * Idempotent — returns the existing finding if this work item already
     * has one (see docs/09-IMPLEMENTATION-PLAN.md Phase 5/6 idempotency
     * precedent; a work item is the accountability unit for exactly one
     * automatic finding, even if it degrades from late to failed).
     */
    public function createAutomaticFinding(WorkItem $workItem, string $findingType): Finding
    {
        $existing = Finding::query()->where('work_item_id', $workItem->id)->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($workItem, $findingType) {
            $finding = Finding::create([
                'source_type' => Finding::SOURCE_AUTOMATIC,
                'finding_type' => $findingType,
                'task_id' => $workItem->task_id,
                'task_checklist_id' => $workItem->task_checklist_id,
                'work_item_id' => $workItem->id,
                'target_department_id' => $workItem->responsible_department_id,
                'target_user_id' => $workItem->assignee_id,
                'title' => $this->titleFor($findingType),
                'status' => Finding::STATUS_WAITING_MANAGER_ACTION,
                'opened_at' => now(),
            ]);

            $this->startManagerSla($finding);

            return $finding;
        });
    }

    public function startManagerSla(Finding $finding): ?SlaInstance
    {
        $manager = $this->resolveResponsibleManager($finding->target_department_id);

        if (! $manager) {
            return null;
        }

        $instance = $this->startSlaInstance($finding, $manager, SlaInstance::TYPE_MANAGER, $this->slaSettings->resolveManagerSla($manager));
        $this->notifications->notifyUser($manager, new FindingEvent($finding, FindingEvent::REQUIRES_MANAGER_ACTION));

        return $instance;
    }

    public function startIsoSla(Finding $finding): ?SlaInstance
    {
        $iso = User::query()->whereHas('roles', fn ($q) => $q->where('code', Role::ISO))->first();

        if (! $iso) {
            return null;
        }

        $instance = $this->startSlaInstance($finding, $iso, SlaInstance::TYPE_ISO, $this->slaSettings->resolveIsoSla());
        $this->notifications->notifyIso(new FindingEvent($finding, FindingEvent::ESCALATED_TO_ISO));

        return $instance;
    }

    /**
     * Auto-resolves a Finding that turns out moot because ISO later
     * approved the PIC's leave request covering that date (docs/superpowers/
     * specs/2026-09-09-pic-leave-request-design.md §3). Completes any
     * still-running SLA instance regardless of type (manager or ISO) since
     * the finding is moot either way.
     */
    public function resolveDueToApprovedLeave(Finding $finding): Finding
    {
        return DB::transaction(function () use ($finding) {
            $finding->update([
                'status' => Finding::STATUS_RESOLVED,
                'resolution_type' => 'leave_approved',
                'resolved_at' => now(),
            ]);

            $finding->slaInstances()
                ->where('status', SlaInstance::STATUS_RUNNING)
                ->update(['status' => SlaInstance::STATUS_COMPLETED, 'completed_at' => now()]);

            return $finding->fresh(['slaInstances']);
        });
    }

    private function startSlaInstance(Finding $finding, User $responsible, string $type, int $minutes): SlaInstance
    {
        $startedAt = now();

        return SlaInstance::create([
            'finding_id' => $finding->id,
            'responsible_user_id' => $responsible->id,
            'sla_type' => $type,
            'effective_minutes' => $minutes,
            'started_at' => $startedAt,
            'due_at' => $this->resolveDueAt($responsible, $startedAt, $minutes),
            'status' => SlaInstance::STATUS_RUNNING,
        ]);
    }

    /**
     * The documented schema has no "owning manager" concept for a
     * department (any Manager of the department may act on a finding, see
     * docs/07-AUTHORIZATION.md §6) — but SLA resolution needs one specific
     * manager's context to check for a personal override (precedence
     * manager > department > global). The first Manager-role member of the
     * department is used for that resolution only; it does not restrict
     * who may respond to the finding (see FindingPolicy::respond).
     */
    private function resolveResponsibleManager(string $departmentId): ?User
    {
        $department = Department::with('users.roles')->find($departmentId);

        return $department?->users->first(fn ($user) => $user->hasRole(Role::MANAGER));
    }

    private function resolveDueAt(User $user, Carbon $startedAt, int $minutes): Carbon
    {
        $calendar = $user->workingCalendar()->with(['hours', 'exceptions'])->first();

        if (! $calendar) {
            return $startedAt->copy()->addMinutes($minutes);
        }

        return $this->calculator->addWorkingMinutes($calendar, $startedAt, $minutes);
    }

    private function titleFor(string $findingType): string
    {
        return match ($findingType) {
            Finding::TYPE_LATE => 'Work item is late',
            Finding::TYPE_FAILED => 'Work item failed to complete',
            default => 'Automatic finding',
        };
    }
}
