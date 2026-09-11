<?php

namespace App\Http\Resources;

use App\Support\EvidenceDisk;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskChecklistResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'task_id' => $this->task_id,
            'title' => $this->title,
            'instructions' => $this->instructions,
            'target_department_id' => $this->target_department_id,
            'schedule_type' => $this->schedule_type,
            'schedule_config' => $this->schedule_config,
            'evidence_min_count' => $this->evidence_min_count,
            'allow_upload' => $this->allow_upload,
            'allow_camera' => $this->allow_camera,
            'works_on_holidays' => $this->works_on_holidays,
            'is_active' => $this->is_active,
            'reference_evidence' => $this->whenLoaded('referenceEvidence', fn () => $this->referenceEvidence->map(fn ($evidence) => [
                'id' => $evidence->id,
                'url' => EvidenceDisk::url($evidence->storage_key),
                'metadata' => $evidence->metadata,
            ])->values()),
            'department_request' => $this->whenLoaded(
                'departmentRequests',
                fn () => $this->departmentRequests->isNotEmpty() ? [
                    'id' => $this->departmentRequests->first()->id,
                    'status' => $this->departmentRequests->first()->status,
                ] : null
            ),
        ];
    }
}
