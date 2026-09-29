<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DepartmentRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'task_id' => $this->task_id,
            'task' => ['id' => $this->task_id, 'title' => $this->whenLoaded('task', fn () => $this->task->title)],
            'task_checklist_id' => $this->task_checklist_id,
            'owner_department_id' => $this->owner_department_id,
            'owner_department' => ['id' => $this->owner_department_id, 'name' => $this->whenLoaded('ownerDepartment', fn () => $this->ownerDepartment->name)],
            'target_department_id' => $this->target_department_id,
            'target_department' => ['id' => $this->target_department_id, 'name' => $this->whenLoaded('targetDepartment', fn () => $this->targetDepartment->name)],
            'target_manager_id' => $this->target_manager_id,
            'target_manager' => ['id' => $this->target_manager_id, 'name' => $this->whenLoaded('targetManager', fn () => $this->targetManager?->name)],
            'status' => $this->status,
            'response_due_at' => $this->response_due_at,
            'assigned_pic_id' => $this->assigned_pic_id,
            'assigned_pic' => ['id' => $this->assigned_pic_id, 'name' => $this->whenLoaded('assignedPic', fn () => $this->assignedPic?->name)],
            'rejection_reason' => $this->rejection_reason,
            'created_at' => $this->created_at,
        ];
    }
}
