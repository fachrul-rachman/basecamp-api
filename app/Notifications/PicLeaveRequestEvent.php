<?php

namespace App\Notifications;

use App\Models\PicLeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * PIC leave-approval workflow notifications (docs/02-BUSINESS-RULES.md §19):
 * ISO is notified of a new submission; the requesting Manager and the PIC
 * are both notified of the review outcome.
 */
class PicLeaveRequestEvent extends Notification
{
    use Queueable;

    public const SUBMITTED = 'submitted';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public function __construct(private PicLeaveRequest $leaveRequest, private string $reason) {}

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
            'pic_leave_request_id' => $this->leaveRequest->id,
            'pic_id' => $this->leaveRequest->pic_id,
            'date_from' => $this->leaveRequest->date_from->toDateString(),
            'date_to' => $this->leaveRequest->date_to->toDateString(),
        ];
    }

    private function titleFor(string $reason): string
    {
        return match ($reason) {
            self::SUBMITTED => 'A new PIC leave request is awaiting your review',
            self::APPROVED => 'A PIC leave request was approved',
            self::REJECTED => 'A PIC leave request was rejected',
            default => 'PIC leave request update',
        };
    }
}
