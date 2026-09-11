<?php

namespace App\Jobs;

use App\Models\Evidence;
use App\Notifications\EvidenceAssessmentEvent;
use App\Services\Ai\EvidenceAssessmentClient;
use App\Services\NotificationDispatcher;
use App\Support\EvidenceDisk;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Compares a newly-uploaded evidence photo against its checklist's
 * reference photos (docs/superpowers/specs/2026-09-10-evidence-ai-assessment-design.md).
 * $tries is fixed at 1 here (not left to the queue worker's own CLI flags)
 * so a failed AI call never retries automatically, in any environment —
 * `ai_status` simply stays null, which this project treats as
 * "not yet analyzed", not an error state.
 */
class AssessEvidenceWithAi implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    /**
     * Belt-and-suspenders alongside the HTTP client's own 30s timeout
     * (OpenAiEvidenceAssessmentClient) — kills the job if anything else in
     * the handler ever hangs, so one stuck evidence never blocks the queue
     * worker indefinitely.
     */
    public int $timeout = 60;

    public function __construct(public Evidence $evidence) {}

    public function handle(EvidenceAssessmentClient $client, NotificationDispatcher $notifications): void
    {
        $evidence = $this->evidence->fresh();

        $evidenceMimeType = $evidence->metadata['mime_type'] ?? null;

        if (! $evidenceMimeType || ! str_starts_with($evidenceMimeType, 'image/')) {
            // Evidence upload isn't restricted to images (StoreWorkItemEvidenceRequest
            // allows any file) — a non-image evidence item simply isn't assessable,
            // not a failure. ai_status is left as-is, same "not yet analyzed"
            // treatment as any other skip/failure case.
            return;
        }

        $workItem = $evidence->submission->workItem;
        $checklist = $workItem->taskChecklist;

        $referenceImages = $checklist->referenceEvidence
            ->map(fn ($ref) => [
                'base64' => base64_encode(EvidenceDisk::disk()->get($ref->storage_key)),
                'mime_type' => $ref->metadata['mime_type'] ?? 'image/jpeg',
            ])
            ->all();
        $evidenceImage = [
            'base64' => base64_encode(EvidenceDisk::disk()->get($evidence->storage_key)),
            'mime_type' => $evidenceMimeType,
        ];
        $context = trim($checklist->title."\n\n".($checklist->instructions ?? ''));

        $result = $client->assess($referenceImages, $evidenceImage, $context);

        $evidence->update([
            'ai_status' => $result['match'] ? 'match' : 'mismatch',
            'ai_score' => $result['confidence'],
            'ai_notes' => $result['notes'],
        ]);

        if (! $result['match']) {
            $notifications->notifyManagersOfDepartment(
                $workItem->responsible_department_id,
                new EvidenceAssessmentEvent($evidence, EvidenceAssessmentEvent::FLAGGED)
            );
        }
    }
}
