<?php

namespace App\Services;

use App\Models\Finding;
use App\Models\FindingAction;
use App\Models\Role;
use App\Models\SlaInstance;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkItem;
use App\Notifications\FindingEvent;
use App\Notifications\WorkItemEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Manager response to a Finding: explanation (-> ISO review) or a
 * one-time reopen (docs/02-BUSINESS-RULES.md §6-7, §13).
 */
class ManagerActionService
{
    public function __construct(
        private AuditLogService $auditLog,
        private NotificationDispatcher $notifications,
    ) {}

    public function explain(User $actor, Finding $finding, string $notes): Finding
    {
        $this->assertFindingActionable($finding);

        return DB::transaction(function () use ($actor, $finding, $notes) {
            $isSelfHandled = $finding->target_user_id === $actor->id;

            $finding->actions()->create([
                'actor_id' => $actor->id,
                'actor_role' => Role::MANAGER,
                'action_type' => FindingAction::EXPLANATION,
                'notes' => $notes,
                'metadata' => ['is_self_handled' => $isSelfHandled],
            ]);

            $finding->update([
                'status' => Finding::STATUS_WAITING_ISO_REVIEW,
                'is_self_handled' => $finding->is_self_handled || $isSelfHandled,
            ]);

            $this->completeManagerSla($finding);

            $this->auditLog->record($actor, 'finding.explanation_submitted', 'Finding', $finding->id, [
                'is_self_handled' => $isSelfHandled,
            ]);

            $fresh = $finding->fresh(['actions', 'slaInstances']);
            $this->notifications->notifyIso(new FindingEvent($fresh, FindingEvent::EXPLANATION_AWAITING_REVIEW));

            return $fresh;
        });
    }

    public function reopen(User $actor, Finding $finding, Carbon $deadlineAt, string $reason): Finding
    {
        $this->assertFindingActionable($finding);

        $workItem = $finding->workItem;

        if (! $workItem) {
            throw ValidationException::withMessages([
                'work_item' => ['This finding has no associated work item to reopen.'],
            ]);
        }

        if ($workItem->reopen()->exists()) {
            throw ValidationException::withMessages([
                'work_item' => ['This work item has already been reopened once (see docs/02-BUSINESS-RULES.md §6).'],
            ]);
        }

        if ($deadlineAt->gt(now()->addDay())) {
            throw ValidationException::withMessages([
                'deadline_at' => ['The reopen deadline may not exceed 24 hours from now.'],
            ]);
        }

        if ($workItem->task->status === Task::STATUS_CANCELLED) {
            throw ValidationException::withMessages([
                'work_item' => ['This task is no longer active; the context is not valid for reopening.'],
            ]);
        }

        return DB::transaction(function () use ($actor, $finding, $workItem, $deadlineAt, $reason) {
            $isSelfHandled = $finding->target_user_id === $actor->id;

            $workItem->reopen()->create([
                'finding_id' => $finding->id,
                'opened_by' => $actor->id,
                'opened_at' => now(),
                'deadline_at' => $deadlineAt,
                'reason' => $reason,
            ]);

            $workItem->update([
                'execution_status' => WorkItem::EXECUTION_IN_PROGRESS,
                'locked_at' => null,
                // compliance_status is deliberately left untouched: the
                // original late/failed fact survives a successful reopen
                // (docs/02-BUSINESS-RULES.md §6, Phase 7 acceptance).
                'reopen_count' => $workItem->reopen_count + 1,
            ]);

            $finding->actions()->create([
                'actor_id' => $actor->id,
                'actor_role' => Role::MANAGER,
                'action_type' => FindingAction::REOPEN,
                'notes' => $reason,
                'metadata' => ['is_self_handled' => $isSelfHandled, 'deadline_at' => $deadlineAt->toIso8601String()],
            ]);

            $finding->update([
                'status' => Finding::STATUS_REOPENED,
                'is_self_handled' => $finding->is_self_handled || $isSelfHandled,
            ]);

            $this->completeManagerSla($finding);

            $this->auditLog->record($actor, 'finding.reopened', 'Finding', $finding->id, [
                'deadline_at' => $deadlineAt->toIso8601String(),
                'is_self_handled' => $isSelfHandled,
            ]);

            $freshWorkItem = $workItem->fresh();
            $this->notifications->notifyUser($freshWorkItem->assignee, new WorkItemEvent($freshWorkItem, WorkItemEvent::REOPENED));

            return $finding->fresh(['actions', 'slaInstances']);
        });
    }

    private function completeManagerSla(Finding $finding): void
    {
        $finding->slaInstances()
            ->where('sla_type', SlaInstance::TYPE_MANAGER)
            ->where('status', SlaInstance::STATUS_RUNNING)
            ->update(['status' => SlaInstance::STATUS_COMPLETED, 'completed_at' => now()]);
    }

    private function assertFindingActionable(Finding $finding): void
    {
        if (! in_array($finding->status, [Finding::STATUS_WAITING_MANAGER_ACTION, Finding::STATUS_ESCALATED], true)) {
            throw ValidationException::withMessages([
                'finding' => ['This finding is not awaiting Manager action.'],
            ]);
        }
    }
}
