<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use App\Models\WorkItem;
use App\Notifications\WorkItemEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkItemAssignmentService
{
    public function __construct(
        private AuditLogService $auditLog,
        private NotificationDispatcher $notifications,
    ) {}

    public function reassign(User $actor, WorkItem $workItem, User $pic, ?string $reason = null): WorkItem
    {
        if ($workItem->locked_at) {
            throw ValidationException::withMessages([
                'work_item' => ['This work item is locked and can no longer be reassigned.'],
            ]);
        }

        $this->assertPicBelongsToDepartment($workItem->responsible_department_id, $pic);

        return DB::transaction(function () use ($actor, $workItem, $pic, $reason) {
            $previousAssigneeId = $workItem->assignee_id;

            $workItem->assignments()->create([
                'from_user_id' => $previousAssigneeId,
                'to_user_id' => $pic->id,
                'changed_by' => $actor->id,
                'reason' => $reason,
            ]);

            $workItem->update(['assignee_id' => $pic->id]);

            $this->auditLog->record($actor, 'work_item.reassigned', 'WorkItem', $workItem->id, [
                'from_user_id' => $previousAssigneeId,
                'to_user_id' => $pic->id,
                'reason' => $reason,
            ]);

            $fresh = $workItem->fresh(['assignee', 'assignments']);
            $this->notifications->notifyUser($fresh->assignee, new WorkItemEvent($fresh, WorkItemEvent::REASSIGNED));

            return $fresh;
        });
    }

    public function assertPicBelongsToDepartment(string $departmentId, User $pic): void
    {
        $isPic = $pic->hasRole(Role::PIC);
        $inDepartment = $pic->departments->pluck('id')->contains($departmentId);

        if (! $isPic || ! $inDepartment) {
            throw ValidationException::withMessages([
                'pic_id' => ['The selected PIC does not belong to the responsible department.'],
            ]);
        }
    }
}
