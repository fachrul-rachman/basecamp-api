<?php

namespace App\Services\Ai;

interface EvidenceAssessmentClient
{
    /**
     * @param  array<int, array{base64: string, mime_type: string}>  $referenceImages
     * @param  array{base64: string, mime_type: string}  $evidenceImage
     * @return array{match: bool, confidence: float, notes: string}
     */
    public function assess(array $referenceImages, array $evidenceImage, string $context): array;
}
