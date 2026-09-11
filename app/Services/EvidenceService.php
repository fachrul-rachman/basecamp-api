<?php

namespace App\Services;

use App\Jobs\AssessEvidenceWithAi;
use App\Models\Evidence;
use App\Models\User;
use App\Models\WorkItem;
use App\Support\EvidenceDisk;
use DateTimeInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class EvidenceService
{
    public function upload(User $actor, WorkItem $workItem, UploadedFile $file, string $sourceType, ?DateTimeInterface $capturedAt): Evidence
    {
        $this->assertEditable($workItem);
        $this->assertSourceAllowed($workItem, $sourceType);

        $submission = $workItem->submission()->firstOrCreate([], ['user_id' => $actor->id]);

        $path = $file->store('work-item-evidence', EvidenceDisk::name());

        $evidence = $submission->evidence()->create([
            'storage_key' => $path,
            'source_type' => $sourceType,
            'captured_at' => $capturedAt,
            'uploaded_at' => now(),
            'metadata' => [
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ],
        ]);

        $this->triggerAiAssessment($evidence, $workItem);

        return $evidence;
    }

    private function triggerAiAssessment(Evidence $evidence, WorkItem $workItem): void
    {
        if (! config('services.evidence_ai.enabled')) {
            return;
        }

        if ($workItem->taskChecklist->referenceEvidence->isEmpty()) {
            $evidence->update(['ai_status' => 'skipped']);

            return;
        }

        AssessEvidenceWithAi::dispatch($evidence);
    }

    public function delete(WorkItem $workItem, Evidence $evidence): void
    {
        $this->assertEditable($workItem);

        EvidenceDisk::disk()->delete($evidence->storage_key);
        $evidence->delete();
    }

    private function assertEditable(WorkItem $workItem): void
    {
        if ($workItem->locked_at) {
            throw ValidationException::withMessages([
                'evidence' => ['This work item is locked; evidence can no longer be changed.'],
            ]);
        }

        if (now()->lt($workItem->available_at)) {
            throw ValidationException::withMessages([
                'evidence' => ['This work item is not available yet.'],
            ]);
        }
    }

    private function assertSourceAllowed(WorkItem $workItem, string $sourceType): void
    {
        $snapshot = $workItem->rule_snapshot ?? [];

        if ($sourceType === Evidence::SOURCE_UPLOAD && ($snapshot['allow_upload'] ?? true) === false) {
            throw ValidationException::withMessages([
                'source_type' => ['File upload is not allowed for this checklist.'],
            ]);
        }

        if ($sourceType === Evidence::SOURCE_CAMERA && ($snapshot['allow_camera'] ?? true) === false) {
            throw ValidationException::withMessages([
                'source_type' => ['Camera capture is not allowed for this checklist.'],
            ]);
        }
    }

    public function review(Evidence $evidence, string $status): Evidence
    {
        if ($evidence->ai_status !== 'mismatch') {
            throw ValidationException::withMessages([
                'evidence' => ['Only an evidence item flagged as a mismatch can be reviewed.'],
            ]);
        }

        if ($evidence->human_review_status !== null) {
            throw ValidationException::withMessages([
                'evidence' => ['This evidence item has already been reviewed.'],
            ]);
        }

        $evidence->update(['human_review_status' => $status]);

        return $evidence->fresh();
    }
}
