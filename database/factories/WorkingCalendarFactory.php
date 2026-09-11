<?php

namespace Database\Factories;

use App\Models\WorkingCalendar;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkingCalendar>
 */
class WorkingCalendarFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ];
    }

    /**
     * Attach a conventional Monday-Friday, 08:00-17:00 schedule.
     */
    public function withOfficeHours(): static
    {
        return $this->afterCreating(function (WorkingCalendar $calendar) {
            foreach (range(0, 6) as $weekday) {
                $isWorkingDay = $weekday >= 1 && $weekday <= 5;

                $calendar->hours()->create([
                    'weekday' => $weekday,
                    'start_time' => $isWorkingDay ? '08:00' : null,
                    'end_time' => $isWorkingDay ? '17:00' : null,
                    'is_working_day' => $isWorkingDay,
                ]);
            }
        });
    }
}
