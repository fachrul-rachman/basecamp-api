<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChecklistAssigneeOverrideResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'task_checklist_id' => $this->task_checklist_id,
            'assignee_id' => $this->assignee_id,
            'starts_at' => $this->starts_at?->toDateString(),
            'ends_at' => $this->ends_at?->toDateString(),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
        ];
    }
}
