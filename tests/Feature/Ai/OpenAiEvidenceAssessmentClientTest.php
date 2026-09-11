<?php

use App\Services\Ai\EvidenceAssessmentClient;
use App\Services\Ai\OpenAiEvidenceAssessmentClient;
use Illuminate\Support\Facades\Http;

test('assess sends the reference images, evidence image, and context to openai and parses the result', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'output' => [
                [
                    'type' => 'message',
                    'content' => [
                        ['type' => 'output_text', 'text' => json_encode(['match' => true, 'confidence' => 0.91, 'notes' => 'Terlihat sesuai referensi.'])],
                    ],
                ],
            ],
        ]),
    ]);

    $client = app(OpenAiEvidenceAssessmentClient::class);
    $result = $client->assess(
        [
            ['base64' => 'ref-base64-1', 'mime_type' => 'image/png'],
            ['base64' => 'ref-base64-2', 'mime_type' => 'image/jpeg'],
        ],
        ['base64' => 'evidence-base64', 'mime_type' => 'image/webp'],
        'Pel lantai lobby'
    );

    expect($result)->toBe(['match' => true, 'confidence' => 0.91, 'notes' => 'Terlihat sesuai referensi.']);

    Http::assertSent(function ($request) {
        $body = $request->data();

        return $request->url() === 'https://api.openai.com/v1/responses'
            && $body['model'] === config('services.openai.evidence_model')
            && $body['input'][0]['role'] === 'system'
            && str_contains($body['input'][0]['content'][0]['text'], 'Nilai secara longgar dan toleran')
            && $body['input'][1]['content'][0]['type'] === 'input_text'
            && $body['input'][1]['content'][0]['text'] === 'Pel lantai lobby'
            && $body['input'][1]['content'][1] === ['type' => 'input_image', 'image_url' => 'data:image/png;base64,ref-base64-1']
            && $body['input'][1]['content'][2] === ['type' => 'input_image', 'image_url' => 'data:image/jpeg;base64,ref-base64-2']
            && $body['input'][1]['content'][3] === ['type' => 'input_image', 'image_url' => 'data:image/webp;base64,evidence-base64']
            && $body['text']['format']['type'] === 'json_schema'
            && $body['text']['format']['strict'] === true
            && $request->hasHeader('Authorization', 'Bearer '.config('services.openai.api_key'));
    });
});

test('assess is bound to the interface via the container', function () {
    expect(app(EvidenceAssessmentClient::class))->toBeInstanceOf(OpenAiEvidenceAssessmentClient::class);
});
