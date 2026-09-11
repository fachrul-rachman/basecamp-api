<?php

namespace App\Notifications;

use App\Models\WorkItem;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * PIC notifications (docs/02-BUSINESS-RULES.md §19): new/reassigned work,
 * reopen, required follow-up (late, still time to fix).
 */
class WorkItemEvent extends Notification
{
    use Queueable;

    public const ASSIGNED = 'assigned';

    public const REASSIGNED = 'reassigned';

    public const REOPENED = 'reopened';

    public const FOLLOW_UP_REQUIRED = 'follow_up_required';

    public function __construct(private WorkItem $workItem, private string $reason) {}

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
            'work_item_id' => $this->workItem->id,
            'task_id' => $this->workItem->task_id,
            'task_checklist_id' => $this->workItem->task_checklist_id,
        ];
    }

    private function titleFor(string $reason): string
    {
        return match ($reason) {
            self::ASSIGNED => 'New work assigned to you',
            self::REASSIGNED => 'Work reassigned to you',
            self::REOPENED => 'Your work has been reopened',
            self::FOLLOW_UP_REQUIRED => 'Your work is late and needs follow-up',
            default => 'Work item update',
        };
    }
}
