<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskService;
use Illuminate\Database\Seeder;

/**
 * Idempotent, additive seeding for Phase 4 demo data (safe to run more
 * than once; does not touch existing Phase 1-3 rows).
 */
class Phase4DemoSeeder extends Seeder
{
    public function run(): void
    {
        $facilities = Department::query()->firstOrCreate(
            ['code' => 'FAC'],
            ['name' => 'Facilities']
        );

        $facilitiesManager = User::query()->where('email', 'facilities.manager@example.com')->first();
        if (! $facilitiesManager) {
            $facilitiesManager = User::factory()->create([
                'name' => 'Facilities Manager Demo',
                'email' => 'facilities.manager@example.com',
            ]);
            $facilitiesManager->roles()->attach(Role::where('code', Role::MANAGER)->first());
            $facilitiesManager->departments()->attach($facilities->id, ['is_primary' => true]);
        }

        if (Task::query()->where('title', 'AC Maintenance Request - Operations')->exists()) {
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
            'title' => 'AC Maintenance Request - Operations',
            'description' => 'Cross-department example: Operations asks Facilities to service the AC unit.',
            'starts_at' => now(),
            'ends_at' => now()->addDays(3),
            'status' => Task::STATUS_ACTIVE,
        ]);

        // Route through the service (not ->create()) so the
        // department_request is created exactly the way the API does it.
        app(TaskService::class)->addChecklist($task, [
            'title' => 'Service AC unit',
            'target_department_id' => $facilities->id,
            'schedule_type' => 'one_time',
            'schedule_config' => ['start_time' => '09:00', 'end_time' => '11:00', 'date' => now()->toDateString()],
            'evidence_min_count' => 1,
        ]);
    }
}
