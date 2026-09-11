<?php

use App\Jobs\AssessEvidenceWithAi;
use App\Notifications\EvidenceAssessmentEvent;
use App\Services\Ai\EvidenceAssessmentClient;
use App\Services\NotificationDispatcher;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

test('the job records a match result and sends no notification', function () {
    Storage::fake('public');
    [$evidence, $manager] = makeEvidenceWithReferenceChecklist();
    Notification::fake();
    Http::fake([
        'api.openai.com/*' => Http::response(['output' => [['type' => 'message', 'content' => [
            ['type' => 'output_text', 'text' => json_encode(['match' => true, 'confidence' => 0.88, 'notes' => 'Sesuai.'])],
        ]]]]),
    ]);

    app(AssessEvidenceWithAi::class, ['evidence' => $evidence])
        ->handle(app(EvidenceAssessmentClient::class), app(NotificationDispatcher::class));

    expect($evidence->fresh())
        ->ai_status->toBe('match')
        ->ai_notes->toBe('Sesuai.');
    expect((float) $evidence->fresh()->ai_score)->toBe(0.88);
    Notification::assertNothingSent();
});

test('the job records a mismatch result and notifies the responsible department managers', function () {
    Storage::fake('public');
    [$evidence, $manager] = makeEvidenceWithReferenceChecklist();
    Notification::fake();
    Http::fake([
        'api.openai.com/*' => Http::response(['output' => [['type' => 'message', 'content' => [
            ['type' => 'output_text', 'text' => json_encode(['match' => false, 'confidence' => 0.35, 'notes' => 'Lokasi terlihat berbeda.'])],
        ]]]]),
    ]);

    app(AssessEvidenceWithAi::class, ['evidence' => $evidence])
        ->handle(app(EvidenceAssessmentClient::class), app(NotificationDispatcher::class));

    expect($evidence->fresh())->ai_status->toBe('mismatch');
    Notification::assertSentTo($manager, EvidenceAssessmentEvent::class, fn ($n) => (function () use ($n) {
        $prop = (new ReflectionClass($n))->getProperty('reason');
        $prop->setAccessible(true);

        return $prop->getValue($n) === EvidenceAssessmentEvent::FLAGGED;
    })());
});

test('the job has no automatic retries', function () {
    [$evidence] = makeEvidenceWithReferenceChecklist();

    expect((new AssessEvidenceWithAi($evidence))->tries)->toBe(1);
});

test('the job leaves ai_status null when the openai call fails, with no custom retry or catch logic', function () {
    Storage::fake('public');
    [$evidence] = makeEvidenceWithReferenceChecklist();
    Http::fake([
        'api.openai.com/*' => Http::response(['error' => 'server error'], 500),
    ]);

    expect(fn () => app(AssessEvidenceWithAi::class, ['evidence' => $evidence])
        ->handle(app(EvidenceAssessmentClient::class), app(NotificationDispatcher::class))
    )->toThrow(RequestException::class);

    expect($evidence->fresh()->ai_status)->toBeNull();
});
