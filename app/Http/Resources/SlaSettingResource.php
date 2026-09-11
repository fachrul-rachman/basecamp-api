<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SlaSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'scope_type' => $this->scope_type,
            'scope_id' => $this->scope_id,
            'minutes' => $this->minutes,
            'is_active' => $this->is_active,
        ];
    }
}
