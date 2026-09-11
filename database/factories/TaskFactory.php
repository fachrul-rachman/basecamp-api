<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'owner_department_id' => Department::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'starts_at' => now(),
            'ends_at' => now()->addDays(7),
            'status' => Task::STATUS_ACTIVE,
        ];
    }
}
