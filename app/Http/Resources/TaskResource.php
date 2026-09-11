<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source_template_id' => $this->source_template_id,
            'owner_department_id' => $this->owner_department_id,
            'title' => $this->title,
            'description' => $this->description,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'overall_deadline_at' => $this->overall_deadline_at,
            'status' => $this->status,
            'checklists' => TaskChecklistResource::collection($this->whenLoaded('checklists')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
