<?php

namespace App\Http\Resources;

use App\Support\EvidenceDisk;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'created_by' => $this->created_by,
            'departments' => $this->whenLoaded('departments', fn () => $this->departments->map(fn ($department) => [
                'id' => $department->id,
                'code' => $department->code,
                'name' => $department->name,
            ])->values()),
            'checklists' => $this->whenLoaded('checklists', fn () => $this->checklists->map(fn ($checklist) => [
                'id' => $checklist->id,
                'title' => $checklist->title,
                'instructions' => $checklist->instructions,
                'target_department_id' => $checklist->target_department_id,
                'schedule_type' => $checklist->schedule_type,
                'schedule_config' => $checklist->schedule_config,
                'evidence_min_count' => $checklist->evidence_min_count,
                'allow_upload' => $checklist->allow_upload,
                'allow_camera' => $checklist->allow_camera,
                'works_on_holidays' => $checklist->works_on_holidays,
                'sort_order' => $checklist->sort_order,
                'reference_evidence' => $checklist->relationLoaded('referenceEvidence')
                    ? $checklist->referenceEvidence->map(fn ($evidence) => [
                        'id' => $evidence->id,
                        'url' => EvidenceDisk::url($evidence->storage_key),
                        'metadata' => $evidence->metadata,
                    ])->values()
                    : [],
            ])->values()),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
