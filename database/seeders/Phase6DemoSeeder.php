<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\WorkItem;
use App\Services\WorkItemAssignmentService;
use Illuminate\Database\Seeder;

/**
 * Idempotent, additive seeding for Phase 6 demo data (safe to run more
 * than once; does not touch existing Phase 1-5 rows).
 */
class Phase6DemoSeeder extends Seeder
{
    public function run(): void
    {
        $managerPic = User::query()->where('email', 'manager.pic@example.com')->first();

        $item = WorkItem::query()
            ->whereHas('task', fn ($q) => $q->where('title', 'Floor Sweeping - Operations'))
            ->whereNull('assignee_id')
            ->first();

        if (! $item || ! $managerPic) {
            return;
        }

        // Assign the demo Manager+PIC user to their own generated Work
        // Item, through the same service the API uses, so the history row
        // is created exactly as it would be via POST /work-items/{id}/reassign.
        app(WorkItemAssignmentService::class)->reassign($managerPic, $item, $managerPic, 'Initial demo assignment');
    }
}
