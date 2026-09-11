<?php

namespace App\Http\Resources;

use App\Support\EvidenceDisk;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FindingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source_type' => $this->source_type,
            'finding_type' => $this->finding_type,
            'task_id' => $this->task_id,
            'task_checklist_id' => $this->task_checklist_id,
            'work_item_id' => $this->work_item_id,
            'target_department_id' => $this->target_department_id,
            'target_user_id' => $this->target_user_id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'resolution_type' => $this->resolution_type,
            'due_at' => $this->due_at,
            'is_self_handled' => $this->is_self_handled,
            'opened_at' => $this->opened_at,
            'resolved_at' => $this->resolved_at,
            'sla' => $this->whenLoaded('slaInstances', fn () => $this->slaInstances->map(fn ($instance) => [
                'sla_type' => $instance->sla_type,
                'status' => $instance->status,
                'effective_minutes' => $instance->effective_minutes,
                'started_at' => $instance->started_at,
                'due_at' => $instance->due_at,
                'completed_at' => $instance->completed_at,
                'breached_at' => $instance->breached_at,
            ])->values()),
            'evidence' => $this->whenLoaded('evidence', fn () => $this->evidence->map(fn ($item) => [
                'id' => $item->id,
                'url' => EvidenceDisk::url($item->storage_key),
                'metadata' => $item->metadata,
                'uploaded_at' => $item->uploaded_at,
            ])->values()),
        ];
    }
}
