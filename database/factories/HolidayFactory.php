<?php

namespace Database\Factories;

use App\Models\Holiday;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Holiday>
 */
class HolidayFactory extends Factory
{
    public function definition(): array
    {
        return [
            'date' => fake()->unique()->dateTimeBetween('now', '+1 year')->format('Y-m-d'),
            'name' => fake()->words(2, true),
            'scope' => Holiday::SCOPE_NATIONAL,
        ];
    }
}
