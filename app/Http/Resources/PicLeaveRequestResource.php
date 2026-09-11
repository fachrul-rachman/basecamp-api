<?php

namespace App\Http\Resources;

use App\Support\EvidenceDisk;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PicLeaveRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pic_id' => $this->pic_id,
            'requested_by' => $this->requested_by,
            'date_from' => $this->date_from->toDateString(),
            'date_to' => $this->date_to->toDateString(),
            'reason' => $this->reason,
            'evidence_url' => $this->storage_key ? EvidenceDisk::url($this->storage_key) : null,
            'status' => $this->status,
            'reviewed_by' => $this->reviewed_by,
            'reviewed_at' => $this->reviewed_at,
            'review_notes' => $this->review_notes,
            'created_at' => $this->created_at,
        ];
    }
}
