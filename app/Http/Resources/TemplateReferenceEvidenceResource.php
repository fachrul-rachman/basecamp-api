<?php

namespace App\Http\Resources;

use App\Support\EvidenceDisk;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TemplateReferenceEvidenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'task_template_checklist_id' => $this->task_template_checklist_id,
            'url' => EvidenceDisk::url($this->storage_key),
            'metadata' => $this->metadata,
        ];
    }
}
