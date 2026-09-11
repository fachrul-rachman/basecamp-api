<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Finding;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Idempotent, additive seeding for Phase 8 demo data (safe to run more
 * than once; does not touch existing Phase 1-7 rows).
 */
class Phase8DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (Finding::query()->where('source_type', Finding::SOURCE_ISO_MANUAL)->exists()) {
            return;
        }

        $operations = Department::query()->where('code', 'OPS')->first();
        $iso = User::query()->where('email', 'iso@example.com')->first();
        $managerPic = User::query()->where('email', 'manager.pic@example.com')->first();

        if (! $operations || ! $iso || ! $managerPic) {
            return;
        }

        Finding::create([
            'source_type' => Finding::SOURCE_ISO_MANUAL,
            'finding_type' => Finding::TYPE_ISO_MANUAL,
            'target_department_id' => $operations->id,
            'target_user_id' => $managerPic->id,
            'created_by' => $iso->id,
            'title' => 'Fire extinguisher inspection tag expired',
            'description' => 'Observed during ISO walkthrough on the Operations floor.',
            'status' => Finding::STATUS_OPEN,
            'due_at' => now()->addDays(3),
            'opened_at' => now(),
        ]);
    }
}
