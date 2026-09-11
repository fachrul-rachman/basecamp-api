<?php

test('evidence can store an ai_notes value', function () {
    [$item, $pic] = makeAvailableWorkItem();
    $submission = $item->submission()->firstOrCreate([], ['user_id' => $pic->id]);

    $evidence = $submission->evidence()->create([
        'storage_key' => 'work-item-evidence/test.jpg',
        'source_type' => 'upload',
        'uploaded_at' => now(),
        'ai_status' => 'mismatch',
        'ai_score' => 0.42,
        'ai_notes' => 'Foto menunjukkan ruangan yang berbeda dari referensi.',
    ]);

    expect($evidence->fresh()->ai_notes)->toBe('Foto menunjukkan ruangan yang berbeda dari referensi.');
});
