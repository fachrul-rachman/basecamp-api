<?php

namespace App\Services;

use App\Models\Finding;
use App\Models\Submission;
use App\Models\User;
use App\Models\WorkItem;
use App\Models\WorkReopen;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Create/update the single current Submission for a Work Item before lock
 * (docs/02-BUSINESS-RULES.md §4). Locking happens either here (PIC marks
 * `submit: true`) or via the periodic evaluation job once the operational
 * window's failure boundary passes untouched.
 *
 * When an active (uncompleted) reopen exists, its own `deadline_at`
 * replaces the work item's original deadline/failure boundary for
 * availability checks — the original columns stay untouched so the
 * late/failed fact survives (docs/02-BUSINESS-RULES.md §6).
 */
class SubmissionService
{
    public function __construct(private AuditLogService $auditLog) {}

    public function save(User $actor, WorkItem $workItem, ?string $notes, bool $submit): Submission
    {
        $activeReopen = $workItem->reopen()->whereNull('completed_at')->first();

        $this->assertEditable($workItem, $activeReopen);

        return DB::transaction(function () use ($actor, $workItem, $notes, $submit, $activeReopen) {
            $submission = $workItem->submission()->firstOrCreate([], ['user_id' => $actor->id]);

            if ($notes !== null) {
                $submission->notes = $notes;
            }

            if ($submit) {
                $this->assertCanFinalize($workItem, $submission);
                $this->finalize($actor, $workItem, $submission, $activeReopen);
            } else {
                $submission->save();

                if ($workItem->execution_status === WorkItem::EXECUTION_PENDING) {
                    $workItem->update(['execution_status' => WorkItem::EXECUTION_IN_PROGRESS]);
                }
            }

            return $submission->fresh('evidence');
        });
    }

    private function finalize(User $actor, WorkItem $workItem, Submission $submission, ?WorkReopen $activeReopen): void
    {
        $now = now();
        $submission->submitted_at = $now;
        $submission->locked_at = $now;
        $submission->save();

        if ($activeReopen) {
            $activeReopen->update(['completed_at' => $now, 'result' => WorkReopen::RESULT_COMPLETED]);

            $workItem->update([
                'execution_status' => WorkItem::EXECUTION_COMPLETED,
                'submitted_at' => $now,
                'completed_at' => $now,
                'locked_at' => $now,
            ]);

            $activeReopen->finding?->update([
                'status' => Finding::STATUS_RESOLVED,
                'resolution_type' => 'reopen_completed',
                'resolved_at' => $now,
            ]);

            $this->auditLog->record($actor, 'work_item.reopen_completed', 'WorkItem', $workItem->id, []);

            return;
        }

        $compliance = $now->gt($workItem->deadline_at)
            ? WorkItem::COMPLIANCE_LATE
            : WorkItem::COMPLIANCE_ON_TIME;

        $workItem->update([
            'execution_status' => WorkItem::EXECUTION_COMPLETED,
            'compliance_status' => $compliance,
            'submitted_at' => $now,
            'completed_at' => $now,
            'locked_at' => $now,
        ]);

        $this->auditLog->record($actor, 'work_item.submitted', 'WorkItem', $workItem->id, [
            'compliance_status' => $compliance,
        ]);
    }

    private function assertEditable(WorkItem $workItem, ?WorkReopen $activeReopen): void
    {
        if ($workItem->locked_at) {
            throw ValidationException::withMessages([
                'submission' => ['This work item is locked and can no longer be edited.'],
            ]);
        }

        if (now()->lt($workItem->available_at)) {
            throw ValidationException::withMessages([
                'submission' => ['This work item is not available yet.'],
            ]);
        }

        $boundary = $activeReopen?->deadline_at ?? $workItem->failure_at;

        if ($boundary && now()->gte($boundary)) {
            throw ValidationException::withMessages([
                'submission' => ['This work item is past its failure boundary.'],
            ]);
        }
    }

    private function assertCanFinalize(WorkItem $workItem, Submission $submission): void
    {
        $evidenceCount = $submission->exists ? $submission->evidence()->count() : 0;

        if ($evidenceCount < $workItem->required_evidence_count) {
            throw ValidationException::withMessages([
                'evidence' => ["At least {$workItem->required_evidence_count} evidence item(s) are required before final submission."],
            ]);
        }
    }
}
