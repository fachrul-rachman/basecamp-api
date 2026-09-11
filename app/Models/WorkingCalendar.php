<?php

namespace App\Models;

use Database\Factories\WorkingCalendarFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'timezone', 'is_active'])]
class WorkingCalendar extends Model
{
    /** @use HasFactory<WorkingCalendarFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<WorkingCalendarHour, $this>
     */
    public function hours(): HasMany
    {
        return $this->hasMany(WorkingCalendarHour::class);
    }

    /**
     * @return HasMany<CalendarException, $this>
     */
    public function exceptions(): HasMany
    {
        return $this->hasMany(CalendarException::class);
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
