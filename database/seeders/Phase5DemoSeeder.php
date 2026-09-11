<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Task;
use App\Models\User;
use App\Services\Scheduling\WorkItemGenerator;
use Illuminate\Database\Seeder;

/**
 * Idempotent, additive seeding for Phase 5 demo data (safe to run more
 * than once; does not touch existing Phase 1-4 rows).
 */
class Phase5DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (Task::query()->where('title', 'Floor Sweeping - Operations')->exists()) {
            $this->runGenerator();

            return;
        }

        $operations = Department::query()->where('code', 'OPS')->first();
        $managerPic = User::query()->where('email', 'manager.pic@example.com')->first();

        if (! $operations || ! $managerPic) {
            return;
        }

        $task = Task::create([
            'owner_department_id' => $operations->id,
            'created_by' => $managerPic->id,
            'title' => 'Floor Sweeping - Operations',
            'description' => 'Recurring daily example for the H-1 Work Item generator.',
            'starts_at' => now()->startOfDay(),
            'ends_at' => now()->addDays(30),
            'status' => Task::STATUS_ACTIVE,
        ]);

        $task->checklists()->create([
            'title' => 'Sweep the main hall',
            'target_department_id' => $operations->id,
            'schedule_type' => 'daily',
            'schedule_config' => ['start_time' => '08:00', 'end_time' => '17:00'],
            'evidence_min_count' => 1,
        ]);

        $this->runGenerator();
    }

    private function runGenerator(): void
    {
        // Simulates tonight's scheduled H-1 run so tomorrow's preview is
        // already visible without waiting for the real scheduler.
        app(WorkItemGenerator::class)->generateForDate(now()->addDay());
    }
}
