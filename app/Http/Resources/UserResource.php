<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'is_active' => $this->is_active,
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->map(fn ($role) => [
                'code' => $role->code,
                'name' => $role->name,
            ])->values()),
            'departments' => $this->whenLoaded('departments', fn () => $this->departments->map(fn ($department) => [
                'id' => $department->id,
                'code' => $department->code,
                'name' => $department->name,
                'is_primary' => (bool) $department->pivot->is_primary,
            ])->values()),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
