<?php

namespace App\Notifications;

use App\Models\Evidence;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * AI evidence-assessment notifications (docs/02-BUSINESS-RULES.md §19):
 * Manager is notified when AI flags an evidence photo as a likely
 * mismatch against its checklist's reference photos.
 */
class EvidenceAssessmentEvent extends Notification
{
    use Queueable;

    public const FLAGGED = 'flagged';

    public function __construct(private Evidence $evidence, private string $reason) {}

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
            'work_item_id' => $this->evidence->submission->workItem->id,
            'evidence_id' => $this->evidence->id,
            'ai_score' => $this->evidence->ai_score,
            'ai_notes' => $this->evidence->ai_notes,
        ];
    }

    private function titleFor(string $reason): string
    {
        return match ($reason) {
            self::FLAGGED => 'AI flagged an evidence photo for your review',
            default => 'Evidence assessment update',
        };
    }
}
