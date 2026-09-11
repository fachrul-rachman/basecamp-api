<?php

namespace Database\Seeders;

use App\Models\Finding;
use App\Models\User;
use App\Models\WorkItem;
use App\Notifications\FindingEvent;
use App\Notifications\WorkItemEvent;
use Illuminate\Database\Seeder;

/**
 * Idempotent, additive seeding for Phase 9 demo data (safe to run more
 * than once; does not touch existing Phase 1-8 rows).
 */
class Phase9DemoSeeder extends Seeder
{
    public function run(): void
    {
        $managerPic = User::query()->where('email', 'manager.pic@example.com')->first();

        if (! $managerPic || $managerPic->notifications()->exists()) {
            return;
        }

        $workItem = WorkItem::query()->where('assignee_id', $managerPic->id)->first();
        if ($workItem) {
            $managerPic->notify(new WorkItemEvent($workItem, WorkItemEvent::ASSIGNED));
        }

        $finding = Finding::query()->where('target_user_id', $managerPic->id)->first();
        if ($finding) {
            $managerPic->notify(new FindingEvent($finding, FindingEvent::REQUIRES_MANAGER_ACTION));
        }
    }
}
