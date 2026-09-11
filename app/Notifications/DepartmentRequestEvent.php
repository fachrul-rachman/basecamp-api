<?php

namespace App\Notifications;

use App\Models\DepartmentRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Manager notification: incoming cross-department request
 * (docs/02-BUSINESS-RULES.md §19).
 */
class DepartmentRequestEvent extends Notification
{
    use Queueable;

    public function __construct(private DepartmentRequest $departmentRequest) {}

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
            'reason' => 'incoming_request',
            'title' => 'New cross-department request',
            'department_request_id' => $this->departmentRequest->id,
            'task_id' => $this->departmentRequest->task_id,
            'owner_department_id' => $this->departmentRequest->owner_department_id,
        ];
    }
}
