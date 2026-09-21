<?php

namespace App\Console\Commands;

use App\Models\Finding;
use App\Models\WorkItem;
use App\Models\WorkReopen;
use App\Notifications\WorkItemEvent;
use App\Services\FindingService;
use App\Services\NotificationDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Passive late/failed evaluation for Work Items the PIC never finished
 * (docs/02-BUSINESS-RULES.md §3). Items actively completed via submission
 * get their compliance_status set at that moment instead (SubmissionService).
 * Also expires reopens whose deadline passed without completion
 * (docs/02-BUSINESS-RULES.md §6).
 */
class EvaluateWorkItems extends Command
{
    protected $signature = 'work-items:evaluate';

    protected $description = 'Mark overdue Work Items late (past deadline_at) or failed (past failure_at), and open Findings for them.';

    public function handle(FindingService $findings, NotificationDispatcher $notifications): int
    {
        $now = now();

        $newlyFailed = WorkItem::query()
            ->whereNotNull('failure_at')
            ->where('failure_at', '<=', $now)
            ->whereNotIn('execution_status', WorkItem::TERMINAL_STATUSES)
            ->get();

        foreach ($newlyFailed as $item) {
            DB::transaction(function () use ($item, $now, $findings) {
                $item->update([
                    'execution_status' => WorkItem::EXECUTION_FAILED,
                    'compliance_status' => WorkItem::COMPLIANCE_FAILED,
                    'locked_at' => $now,
                ]);
                $findings->createAutomaticFinding($item, Finding::TYPE_FAILED);
            });
        }

        $newlyLate = WorkItem::query()
            ->whereNotNull('deadline_at')
            ->whereNotNull('failure_at')
            ->where('deadline_at', '<=', $now)
            ->where('failure_at', '>', $now)
            ->where('compliance_status', WorkItem::COMPLIANCE_PENDING)
            ->whereNotIn('execution_status', WorkItem::TERMINAL_STATUSES)
            ->get();

        foreach ($newlyLate as $item) {
            DB::transaction(function () use ($item, $findings, $notifications) {
                $item->update(['compliance_status' => WorkItem::COMPLIANCE_LATE]);
                $findings->createAutomaticFinding($item, Finding::TYPE_LATE);
                $notifications->notifyUser($item->assignee, new WorkItemEvent($item, WorkItemEvent::FOLLOW_UP_REQUIRED));
            });
        }

        $expiredReopens = WorkReopen::query()
            ->whereNull('completed_at')
            ->where('deadline_at', '<=', $now)
            ->get();

        foreach ($expiredReopens as $reopen) {
            DB::transaction(function () use ($reopen, $now) {
                $reopen->update(['completed_at' => $now, 'result' => WorkReopen::RESULT_MISSED]);

                $reopen->workItem?->update([
                    'execution_status' => WorkItem::EXECUTION_FAILED,
                    'locked_at' => $now,
                ]);

                // The reopen attempt did not fix it; the Manager must act
                // again (their one reopen is already used, see
                // ManagerActionService::reopen()).
                $reopen->finding?->update(['status' => Finding::STATUS_WAITING_MANAGER_ACTION]);
            });
        }

        $this->info(sprintf(
            'Failed: %d, Late: %d, Reopens missed: %d.',
            $newlyFailed->count(),
            $newlyLate->count(),
            $expiredReopens->count()
        ));

        return self::SUCCESS;
    }
}
