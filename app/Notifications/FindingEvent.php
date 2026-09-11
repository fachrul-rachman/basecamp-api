<?php

namespace App\Notifications;

use App\Models\Finding;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Manager notifications (docs/02-BUSINESS-RULES.md §19): PIC finding,
 * ISO manual finding, ISO feedback/reopen. ISO notifications: Manager SLA
 * escalation, explanation awaiting review, manual finding response
 * awaiting review. Director gets no push notification by design.
 */
class FindingEvent extends Notification
{
    use Queueable;

    public const REQUIRES_MANAGER_ACTION = 'requires_manager_action';

    public const ESCALATED_TO_ISO = 'escalated_to_iso';

    public const EXPLANATION_AWAITING_REVIEW = 'explanation_awaiting_review';

    public const ISO_FEEDBACK = 'iso_feedback';

    public const AUDIT_ASSIGNED = 'audit_assigned';

    public const AUDIT_RESPONSE_AWAITING_REVIEW = 'audit_response_awaiting_review';

    public function __construct(private Finding $finding, private string $reason) {}

    /**
     * @return string[]
     */
    public function via(mixed $notifiable): array
    {
        return ['database'];
    }

    public function toArray(mixed $notifiable): array
    {
        return [
            'reason' => $this->reason,
            'title' => $this->titleFor($this->reason),
            'finding_id' => $this->finding->id,
            'source_type' => $this->finding->source_type,
            'target_department_id' => $this->finding->target_department_id,
        ];
    }

    private function titleFor(string $reason): string
    {
        return match ($reason) {
            self::REQUIRES_MANAGER_ACTION => 'A finding requires your action',
            self::ESCALATED_TO_ISO => 'A Manager SLA has been escalated to you',
            self::EXPLANATION_AWAITING_REVIEW => 'An explanation is awaiting your review',
            self::ISO_FEEDBACK => 'ISO has reviewed a finding you handled',
            self::AUDIT_ASSIGNED => 'A manual audit finding has been assigned to you',
            self::AUDIT_RESPONSE_AWAITING_REVIEW => 'A manager response is awaiting your review',
            default => 'Finding update',
        };
    }
}
