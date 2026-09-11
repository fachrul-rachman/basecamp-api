<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['working_calendar_id', 'date', 'is_working', 'start_time', 'end_time', 'reason'])]
class CalendarException extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_working' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<WorkingCalendar, $this>
     */
    public function workingCalendar(): BelongsTo
    {
        return $this->belongsTo(WorkingCalendar::class);
    }
}
