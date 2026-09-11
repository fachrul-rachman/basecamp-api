<?php

use App\Jobs\AssessEvidenceWithAi;
use App\Models\Department;
use App\Models\Evidence;
use App\Services\EvidenceService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;

test('uploading evidence for a checklist with reference photos dispatches the ai job', function () {
    Queue::fake();
    $department = Department::factory()->create();
    [$item, $pic] = makeAvailableWorkItem(['target_department_id' => $department->id]);
    $item->taskChecklist->referenceEvidence()->create([
        'storage_key' => 'checklist-reference-evidence/ref.jpg',
        'metadata' => [],
    ]);

    $evidence = app(EvidenceService::class)->upload(
        $pic, $item, UploadedFile::fake()->image('photo.jpg'), Evidence::SOURCE_UPLOAD, null
    );

    Queue::assertPushed(AssessEvidenceWithAi::class, fn ($job) => $job->evidence->id === $evidence->id);
    expect($evidence->ai_status)->toBeNull();
});

test('uploading evidence for a checklist with no reference photos is marked skipped and dispatches no job', function () {
    Queue::fake();
    [$item, $pic] = makeAvailableWorkItem();

    $evidence = app(EvidenceService::class)->upload(
        $pic, $item, UploadedFile::fake()->image('photo.jpg'), Evidence::SOURCE_UPLOAD, null
    );

    Queue::assertNotPushed(AssessEvidenceWithAi::class);
    expect($evidence->fresh()->ai_status)->toBe('skipped');
});

test('the ai kill switch prevents any job dispatch even with reference photos', function () {
    config(['services.evidence_ai.enabled' => false]);
    Queue::fake();
    [$item, $pic] = makeAvailableWorkItem();
    $item->taskChecklist->referenceEvidence()->create([
        'storage_key' => 'checklist-reference-evidence/ref.jpg',
        'metadata' => [],
    ]);

    $evidence = app(EvidenceService::class)->upload(
        $pic, $item, UploadedFile::fake()->image('photo.jpg'), Evidence::SOURCE_UPLOAD, null
    );

    Queue::assertNotPushed(AssessEvidenceWithAi::class);
    expect($evidence->fresh()->ai_status)->toBeNull();
});
