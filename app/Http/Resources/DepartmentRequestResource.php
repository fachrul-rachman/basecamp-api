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
            'task_checklist_id' => $this->task_checklist_id,
            'owner_department_id' => $this->owner_department_id,
            'target_department_id' => $this->target_department_id,
            'target_manager_id' => $this->target_manager_id,
            'status' => $this->status,
            'response_due_at' => $this->response_due_at,
            'assigned_pic_id' => $this->assigned_pic_id,
            'rejection_reason' => $this->rejection_reason,
            'created_at' => $this->created_at,
        ];
    }
}
