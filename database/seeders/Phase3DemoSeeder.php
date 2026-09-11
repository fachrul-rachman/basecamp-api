<?php

namespace Database\Seeders;

use App\Models\TaskTemplate;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Idempotent, additive seeding for Phase 3 demo data (safe to run more
 * than once; does not touch existing Phase 1/2 rows).
 */
class Phase3DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (TaskTemplate::query()->where('name', 'Daily Toilet Cleaning')->exists()) {
            return;
        }

        $admin = User::query()->where('email', 'admin@example.com')->first();

        $template = TaskTemplate::create([
            'name' => 'Daily Toilet Cleaning',
            'description' => 'Standard daily restroom cleaning checklist.',
            'created_by' => $admin?->id,
        ]);

        $template->checklists()->create([
            'title' => 'Clean and mop the floor',
            'instructions' => 'Sweep, mop, and check for standing water.',
            'schedule_type' => 'daily',
            'schedule_config' => ['start_time' => '08:00', 'end_time' => '10:00'],
            'evidence_min_count' => 1,
        ]);

        $template->checklists()->create([
            'title' => 'Restock soap and tissue',
            'schedule_type' => 'weekly',
            'schedule_config' => ['start_time' => '08:00', 'end_time' => '09:00', 'weekdays' => [1, 4]],
            'evidence_min_count' => 1,
        ]);
    }
}
