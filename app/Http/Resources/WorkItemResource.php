<?php

namespace App\Http\Resources;

use App\Models\WorkItem;
use App\Support\EvidenceDisk;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class WorkItemResource extends JsonResource
{
    private const TERMINAL_STATUSES = [
        WorkItem::EXECUTION_COMPLETED,
        WorkItem::EXECUTION_FAILED,
        WorkItem::EXECUTION_CANCELLED,
    ];

    public function toArray(Request $request): array
    {
        $now = Carbon::now();

        return [
            'id' => $this->id,
            'operational_date' => $this->operational_date?->toDateString(),
            'period_start' => $this->period_start,
            'period_end' => $this->period_end,
            'date_bucket' => $this->dateBucket(),
            'execution_status' => $this->execution_status,
            'compliance_status' => $this->compliance_status,
            'available_at' => $this->available_at,
            'deadline_at' => $this->deadline_at,
            'failure_at' => $this->failure_at,
            'can_submit' => $this->canSubmit($now),
            'can_edit' => $this->canSubmit($now),
            'is_late' => $this->isLate($now),
            'required_evidence_count' => $this->required_evidence_count,
            'submitted_evidence_count' => $this->whenLoaded(
                'submission',
                fn () => $this->submission?->relationLoaded('evidence') ? $this->submission->evidence->count() : 0,
                0
            ),
            'reopen' => null,
            'assignee_id' => $this->assignee_id,
            'submission' => $this->whenLoaded('submission', fn () => $this->submission ? [
                'id' => $this->submission->id,
                'notes' => $this->submission->notes,
                'submitted_at' => $this->submission->submitted_at,
                'locked_at' => $this->submission->locked_at,
                'evidence' => $this->submission->relationLoaded('evidence') ? $this->submission->evidence->map(fn ($evidence) => [
                    'id' => $evidence->id,
                    'url' => EvidenceDisk::url($evidence->storage_key),
                    'source_type' => $evidence->source_type,
                    'captured_at' => $evidence->captured_at,
                    'uploaded_at' => $evidence->uploaded_at,
                ])->values() : [],
            ] : null),
            'task' => $this->whenLoaded('task', fn () => [
                'id' => $this->task->id,
                'title' => $this->task->title,
            ]),
            'checklist' => $this->whenLoaded('taskChecklist', fn () => [
                'id' => $this->taskChecklist->id,
                'title' => $this->taskChecklist->title,
                'instructions' => $this->taskChecklist->instructions,
            ]),
        ];
    }

    private function dateBucket(): ?string
    {
        $today = Carbon::today()->toDateString();
        $tomorrow = Carbon::tomorrow()->toDateString();

        if ($this->operational_date) {
            $date = $this->operational_date->toDateString();

            return match (true) {
                $date === $today => 'today',
                $date === $tomorrow => 'tomorrow',
                $date < $today => 'past',
                default => 'future',
            };
        }

        if ($this->period_start && $this->period_end) {
            if ($this->period_start->toDateString() === $tomorrow) {
                return 'tomorrow';
            }

            if ($this->period_start->toDateString() <= $today && $this->period_end->toDateString() >= $today) {
                return 'today';
            }
        }

        return null;
    }

    private function canSubmit(Carbon $now): bool
    {
        if (in_array($this->execution_status, self::TERMINAL_STATUSES, true)) {
            return false;
        }

        if ($now->lt($this->available_at)) {
            return false;
        }

        if ($this->failure_at && $now->gte($this->failure_at)) {
            return false;
        }

        return true;
    }

    private function isLate(Carbon $now): bool
    {
        if (! $this->deadline_at || ! $this->failure_at) {
            return false;
        }

        if (in_array($this->execution_status, self::TERMINAL_STATUSES, true)) {
            return $this->compliance_status === WorkItem::COMPLIANCE_LATE;
        }

        return $now->gt($this->deadline_at) && $now->lt($this->failure_at);
    }
}
