<?php

namespace App\Services;

use App\Models\ChecklistAssigneeOverride;
use App\Models\DepartmentRequest;
use App\Models\TaskChecklist;
use App\Models\User;
use App\Support\Scheduling\ScheduleType;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Owns Checklist-level assignment (docs/superpowers/specs/2026-09-22-
 * checklist-assignment-and-generator-frequency-design.md) — the default/
 * current PIC for an own-department, non-weekly_quota Checklist, plus
 * bounded temporary replacements. Deliberately does NOT apply to:
 * - weekly_quota checklists (multiple concurrent Work Items per period are
 *   independently assignable via the existing per-Work-Item reassign flow);
 * - cross-department checklists (DepartmentRequest.assigned_pic_id stays
 *   the sole source of truth there).
 */
class ChecklistAssignmentService
{
    public function __construct(private WorkItemAssignmentService $workItemAssignments) {}

    public function setDefaultAssignee(User $actor, TaskChecklist $checklist, User $pic): TaskChecklist
    {
        $this->assertAssignable($checklist);
        $this->workItemAssignments->assertPicBelongsToDepartment($checklist->target_department_id, $pic);

        return DB::transaction(function () use ($actor, $checklist, $pic) {
            $checklist->update(['default_assignee_id' => $pic->id]);

            $checklist->assigneeOverrides()
                ->where('ends_at', '>=', now()->toDateString())
                ->delete();

            $checklist->workItems()->whereNull('locked_at')->get()
                ->each(fn ($item) => $this->workItemAssignments->reassign(
                    $actor, $item, $pic, 'Checklist default assignee changed'
                ));

            return $checklist->fresh(['defaultAssignee']);
        });
    }

    public function createTemporaryOverride(
        User $actor,
        TaskChecklist $checklist,
        User $pic,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt
    ): ChecklistAssigneeOverride {
        $this->assertAssignable($checklist);
        $this->workItemAssignments->assertPicBelongsToDepartment($checklist->target_department_id, $pic);

        $overlaps = $checklist->assigneeOverrides()
            ->where('starts_at', '<=', $endsAt->toDateString())
            ->where('ends_at', '>=', $startsAt->toDateString())
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages([
                'starts_at' => ['This period overlaps an existing assignment override for this checklist.'],
            ]);
        }

        return DB::transaction(function () use ($actor, $checklist, $pic, $startsAt, $endsAt) {
            $override = $checklist->assigneeOverrides()->create([
                'assignee_id' => $pic->id,
                'starts_at' => $startsAt->toDateString(),
                'ends_at' => $endsAt->toDateString(),
                'created_by' => $actor->id,
            ]);

            $checklist->workItems()
                ->whereNull('locked_at')
                ->whereNotNull('operational_date')
                ->whereDate('operational_date', '>=', $startsAt->toDateString())
                ->whereDate('operational_date', '<=', $endsAt->toDateString())
                ->get()
                ->each(fn ($item) => $this->workItemAssignments->reassign(
                    $actor, $item, $pic, 'Temporary assignee override'
                ));

            return $override->fresh(['assignee']);
        });
    }

    public function resolveEffectiveAssignee(TaskChecklist $checklist, CarbonInterface $date): ?string
    {
        if ($checklist->schedule_type === ScheduleType::WeeklyQuota->value) {
            return null;
        }

        $request = $checklist->departmentRequests->first();

        if ($request) {
            return $request->status === DepartmentRequest::STATUS_ASSIGNED ? $request->assigned_pic_id : null;
        }

        $override = $checklist->assigneeOverrides->first(
            fn ($candidate) => $candidate->starts_at->toDateString() <= $date->toDateString()
                && $candidate->ends_at->toDateString() >= $date->toDateString()
        );

        return $override?->assignee_id ?? $checklist->default_assignee_id;
    }

    private function assertAssignable(TaskChecklist $checklist): void
    {
        if ($checklist->schedule_type === ScheduleType::WeeklyQuota->value) {
            throw ValidationException::withMessages([
                'checklist' => ['weekly_quota checklists are assigned per Work Item, not at the checklist level.'],
            ]);
        }

        if ($checklist->departmentRequests->isNotEmpty()) {
            throw ValidationException::withMessages([
                'checklist' => ['Cross-department checklists are assigned via the department request, not here.'],
            ]);
        }
    }
}
