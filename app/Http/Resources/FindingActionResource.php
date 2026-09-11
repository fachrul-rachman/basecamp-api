<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FindingActionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'actor_id' => $this->actor_id,
            'actor_role' => $this->actor_role,
            'action_type' => $this->action_type,
            'notes' => $this->notes,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
        ];
    }
}
