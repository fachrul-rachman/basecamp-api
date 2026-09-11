<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkingCalendarResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'timezone' => $this->timezone,
            'is_active' => $this->is_active,
            'hours' => $this->whenLoaded('hours', fn () => $this->hours->map(fn ($hour) => [
                'weekday' => $hour->weekday,
                'is_working_day' => $hour->is_working_day,
                'start_time' => $hour->start_time,
                'end_time' => $hour->end_time,
            ])->values()),
            'exceptions' => $this->whenLoaded('exceptions', fn () => $this->exceptions->map(fn ($exception) => [
                'id' => $exception->id,
                'date' => $exception->date->toDateString(),
                'is_working' => $exception->is_working,
                'start_time' => $exception->start_time,
                'end_time' => $exception->end_time,
                'reason' => $exception->reason,
            ])->values()),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
