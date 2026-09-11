<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;

/**
 * Calls OpenAI's Responses API (docs/superpowers/specs/2026-09-10-evidence-ai-assessment-design.md
 * §5) with a lenient-tone system instruction, the checklist's own
 * title/instructions as context, all reference photos, and the new
 * evidence photo — all images sent as base64 data URIs, never a hosted
 * URL, so this works identically for local and R2-backed evidence storage
 * and never races a signed URL's expiry.
 */
class OpenAiEvidenceAssessmentClient implements EvidenceAssessmentClient
{
    private const SYSTEM_INSTRUCTION = <<<'TEXT'
        Anda meninjau foto bukti kerja untuk sistem akuntabilitas operasional.
        Bandingkan foto yang baru diunggah dengan foto referensi yang diberikan.
        Nilai secara longgar dan toleran: anggap match true kecuali fotonya
        jelas-jelas berbeda subjek, lokasi, atau jenis pekerjaannya dari
        referensi. Perbedaan kecil seperti sudut foto, pencahayaan, kualitas
        kamera, atau variasi wajar dalam cara tugas dikerjakan BUKAN alasan
        untuk match false. Jika ragu-ragu, pilih match true. Isi notes dengan
        Bahasa Indonesia, singkat tapi jelas, terutama jelaskan alasannya saat
        match false.
        TEXT;

    /**
     * @param  array<int, array{base64: string, mime_type: string}>  $referenceImages
     * @param  array{base64: string, mime_type: string}  $evidenceImage
     * @return array{match: bool, confidence: float, notes: string}
     */
    public function assess(array $referenceImages, array $evidenceImage, string $context): array
    {
        $imageContent = array_map(
            fn (array $image) => ['type' => 'input_image', 'image_url' => "data:{$image['mime_type']};base64,{$image['base64']}"],
            [...$referenceImages, $evidenceImage]
        );

        $response = Http::withToken(config('services.openai.api_key'))
            ->connectTimeout(10)
            ->timeout(30)
            ->post('https://api.openai.com/v1/responses', [
                'model' => config('services.openai.evidence_model'),
                'input' => [
                    [
                        'role' => 'system',
                        'content' => [['type' => 'input_text', 'text' => self::SYSTEM_INSTRUCTION]],
                    ],
                    [
                        'role' => 'user',
                        'content' => [
                            ['type' => 'input_text', 'text' => $context],
                            ...$imageContent,
                        ],
                    ],
                ],
                'text' => [
                    'format' => [
                        'type' => 'json_schema',
                        'strict' => true,
                        'name' => 'evidence_assessment',
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'match' => ['type' => 'boolean'],
                                'confidence' => ['type' => 'number'],
                                'notes' => ['type' => 'string'],
                            ],
                            'required' => ['match', 'confidence', 'notes'],
                            'additionalProperties' => false,
                        ],
                    ],
                ],
            ])
            ->throw();

        return json_decode($this->extractOutputText($response->json()), true);
    }

    private function extractOutputText(array $body): string
    {
        foreach ($body['output'] ?? [] as $item) {
            if (($item['type'] ?? null) !== 'message') {
                continue;
            }

            foreach ($item['content'] ?? [] as $content) {
                if (($content['type'] ?? null) === 'output_text') {
                    return $content['text'];
                }
            }
        }

        throw new \RuntimeException('OpenAI response contained no output_text content.');
    }
}
