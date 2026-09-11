<?php

namespace App\Services;

use App\Models\Finding;
use App\Models\FindingAction;
use App\Models\Role;
use App\Models\User;
use App\Notifications\FindingEvent;
use Illuminate\Validation\ValidationException;

class IsoReviewService
{
    public function __construct(
        private AuditLogService $auditLog,
        private NotificationDispatcher $notifications,
    ) {}

    public function review(User $actor, Finding $finding, string $decision, ?string $notes): Finding
    {
        if ($finding->status !== Finding::STATUS_WAITING_ISO_REVIEW) {
            throw ValidationException::withMessages([
                'finding' => ['This finding is not awaiting ISO review.'],
            ]);
        }

        $finding->actions()->create([
            'actor_id' => $actor->id,
            'actor_role' => Role::ISO,
            'action_type' => FindingAction::ISO_REVIEW,
            'notes' => $notes,
            'metadata' => ['decision' => $decision],
        ]);

        if ($decision === 'accept') {
            // Original compliance fact is preserved — only the finding's
            // own resolution changes (docs/06-API-CONTRACT.md §9).
            $finding->update([
                'status' => Finding::STATUS_RESOLVED,
                'resolution_type' => 'explanation_accepted',
                'resolved_at' => now(),
            ]);
        } else {
            $finding->update(['status' => Finding::STATUS_WAITING_MANAGER_ACTION]);
        }

        $this->auditLog->record($actor, 'finding.iso_reviewed', 'Finding', $finding->id, ['decision' => $decision]);

        $fresh = $finding->fresh('actions');

        $explainingManagerId = $fresh->actions
            ->where('action_type', FindingAction::EXPLANATION)
            ->last()
            ?->actor_id;

        $this->notifications->notifyUser(
            $explainingManagerId ? User::find($explainingManagerId) : null,
            new FindingEvent($fresh, FindingEvent::ISO_FEEDBACK)
        );

        return $fresh;
    }
}
