<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

/**
 * Idempotent, additive seeding for Phase 7 demo data (safe to run more
 * than once; does not touch existing Phase 1-6 rows). Creates an
 * already-overdue Work Item assigned to the Manager+PIC demo user, so
 * running the evaluation job produces a self-handled Finding ready to
 * exercise explanation/reopen/ISO-review manually.
 */
class Phase7DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (Task::query()->where('title', 'Overdue Demo Task - Operations')->exists()) {
            Artisan::call('work-items:evaluate');

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
            'title' => 'Overdue Demo Task - Operations',
            'description' => 'Deliberately overdue example for the Phase 7 Finding/SLA/reopen flow.',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(30),
        ]);

        $checklist = $task->checklists()->create([
            'title' => 'Submit yesterday\'s inspection report',
            'target_department_id' => $operations->id,
            'schedule_type' => 'daily',
            'schedule_config' => ['start_time' => '08:00', 'end_time' => '17:00'],
        ]);

        WorkItem::create([
            'task_id' => $task->id,
            'task_checklist_id' => $checklist->id,
            'responsible_department_id' => $operations->id,
            'assignee_id' => $managerPic->id,
            'operational_date' => now()->subDay()->toDateString(),
            'available_at' => now()->subDay(),
            'deadline_at' => now()->subHours(5),
            'failure_at' => now()->subHour(),
        ]);

        Artisan::call('work-items:evaluate');
    }
}
