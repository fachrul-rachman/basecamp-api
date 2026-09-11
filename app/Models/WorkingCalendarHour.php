<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['working_calendar_id', 'weekday', 'start_time', 'end_time', 'is_working_day'])]
class WorkingCalendarHour extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'is_working_day' => 'boolean',
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
