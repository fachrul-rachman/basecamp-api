<?php

namespace App\Services;

use App\Models\DepartmentRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\Scheduling\WorkItemGenerator;
use App\Support\Scheduling\ScheduleType;
use Illuminate\Validation\ValidationException;

class DepartmentRequestService
{
    public function __construct(
        private AuditLogService $auditLog,
        private WorkItemGenerator $workItemGenerator,
    ) {}

    public function assign(User $actor, DepartmentRequest $departmentRequest, User $pic): DepartmentRequest
    {
        $this->assertPicBelongsToTargetDepartment($departmentRequest, $pic);

        $departmentRequest->update([
            'status' => DepartmentRequest::STATUS_ASSIGNED,
            'assigned_pic_id' => $pic->id,
            'target_manager_id' => $actor->id,
        ]);

        // event-type checklists have no H-1 schedule to generate from; the
        // Work Item is created here, the moment a PIC becomes assignable
        // (see docs/05-DATABASE-SCHEMA.md §20).
        $checklist = $departmentRequest->taskChecklist;
        if ($checklist && $checklist->schedule_type === ScheduleType::Event->value) {
            $this->workItemGenerator->generateForEvent($checklist, $pic->id, $departmentRequest->id);
        }

        $this->auditLog->record($actor, 'department_request.assigned', 'DepartmentRequest', $departmentRequest->id, [
            'pic_id' => $pic->id,
        ]);

        return $departmentRequest->fresh(['assignedPic', 'targetManager']);
    }

    public function reject(User $actor, DepartmentRequest $departmentRequest, string $reason): DepartmentRequest
    {
        $departmentRequest->update([
            'status' => DepartmentRequest::STATUS_REJECTED,
            'rejection_reason' => $reason,
            'target_manager_id' => $actor->id,
        ]);

        $this->auditLog->record($actor, 'department_request.rejected', 'DepartmentRequest', $departmentRequest->id, [
            'reason' => $reason,
        ]);

        return $departmentRequest->fresh();
    }

    /**
     * Changes who is assigned on an already-assigned request. No dedicated
     * history table exists for this in the documented schema (only
     * `work_item_assignments`, which is Work-Item specific and doesn't
     * exist until Phase 5/6), so the from/to PIC and reason are preserved
     * via the audit log instead (see docs/03-DOMAIN-MODEL.md §16,
     * docs/08-BACKEND-ARCHITECTURE.md §9).
     */
    public function reassign(User $actor, DepartmentRequest $departmentRequest, User $pic, ?string $reason = null): DepartmentRequest
    {
        $this->assertPicBelongsToTargetDepartment($departmentRequest, $pic);

        $previousPicId = $departmentRequest->assigned_pic_id;

        $departmentRequest->update([
            'status' => DepartmentRequest::STATUS_ASSIGNED,
            'assigned_pic_id' => $pic->id,
            'target_manager_id' => $actor->id,
        ]);

        $this->auditLog->record($actor, 'department_request.reassigned', 'DepartmentRequest', $departmentRequest->id, [
            'from_pic_id' => $previousPicId,
            'to_pic_id' => $pic->id,
            'reason' => $reason,
        ]);

        return $departmentRequest->fresh(['assignedPic']);
    }

    private function assertPicBelongsToTargetDepartment(DepartmentRequest $departmentRequest, User $pic): void
    {
        $isPic = $pic->hasRole(Role::PIC);
        $inDepartment = $pic->departments->pluck('id')->contains($departmentRequest->target_department_id);

        if (! $isPic || ! $inDepartment) {
            throw ValidationException::withMessages([
                'pic_id' => ['The selected PIC does not belong to the target department.'],
            ]);
        }
    }
}
