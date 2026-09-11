<?php

use App\Models\Department;

test('a manager can confirm a mismatched evidence item in their department', function () {
    $department = Department::factory()->create();
    $manager = makeManager($department);
    [$item, $pic] = makeAvailableWorkItem(['target_department_id' => $department->id]);
    $item->update(['responsible_department_id' => $department->id]);
    $submission = $item->submission()->firstOrCreate([], ['user_id' => $pic->id]);
    $evidence = $submission->evidence()->create([
        'storage_key' => 'work-item-evidence/x.jpg',
        'source_type' => 'upload',
        'uploaded_at' => now(),
        'ai_status' => 'mismatch',
        'ai_score' => 0.2,
        'ai_notes' => 'Beda lokasi.',
    ]);

    $response = $this->actingAs($manager, 'sanctum')
        ->postJson("/api/v1/work-items/{$item->id}/evidence/{$evidence->id}/review", ['status' => 'confirmed']);

    $response->assertOk();
    expect($evidence->fresh()->human_review_status)->toBe('confirmed');
});

test('a manager outside the department cannot review the evidence', function () {
    $department = Department::factory()->create();
    $outsider = makeManager(Department::factory()->create());
    [$item, $pic] = makeAvailableWorkItem(['target_department_id' => $department->id]);
    $item->update(['responsible_department_id' => $department->id]);
    $submission = $item->submission()->firstOrCreate([], ['user_id' => $pic->id]);
    $evidence = $submission->evidence()->create([
        'storage_key' => 'work-item-evidence/x.jpg',
        'source_type' => 'upload',
        'uploaded_at' => now(),
        'ai_status' => 'mismatch',
    ]);

    $this->actingAs($outsider, 'sanctum')
        ->postJson("/api/v1/work-items/{$item->id}/evidence/{$evidence->id}/review", ['status' => 'confirmed'])
        ->assertForbidden();
});

test('reviewing an evidence item that is not a mismatch is rejected', function () {
    $department = Department::factory()->create();
    $manager = makeManager($department);
    [$item, $pic] = makeAvailableWorkItem(['target_department_id' => $department->id]);
    $item->update(['responsible_department_id' => $department->id]);
    $submission = $item->submission()->firstOrCreate([], ['user_id' => $pic->id]);
    $evidence = $submission->evidence()->create([
        'storage_key' => 'work-item-evidence/x.jpg',
        'source_type' => 'upload',
        'uploaded_at' => now(),
        'ai_status' => 'match',
    ]);

    $this->actingAs($manager, 'sanctum')
        ->postJson("/api/v1/work-items/{$item->id}/evidence/{$evidence->id}/review", ['status' => 'confirmed'])
        ->assertUnprocessable();
});

test('an evidence item cannot be reviewed twice', function () {
    $department = Department::factory()->create();
    $manager = makeManager($department);
    [$item, $pic] = makeAvailableWorkItem(['target_department_id' => $department->id]);
    $item->update(['responsible_department_id' => $department->id]);
    $submission = $item->submission()->firstOrCreate([], ['user_id' => $pic->id]);
    $evidence = $submission->evidence()->create([
        'storage_key' => 'work-item-evidence/x.jpg',
        'source_type' => 'upload',
        'uploaded_at' => now(),
        'ai_status' => 'mismatch',
        'human_review_status' => 'dismissed',
    ]);

    $this->actingAs($manager, 'sanctum')
        ->postJson("/api/v1/work-items/{$item->id}/evidence/{$evidence->id}/review", ['status' => 'confirmed'])
        ->assertUnprocessable();
});

test('a pic cannot review evidence', function () {
    $department = Department::factory()->create();
    [$item, $pic] = makeAvailableWorkItem(['target_department_id' => $department->id]);
    $submission = $item->submission()->firstOrCreate([], ['user_id' => $pic->id]);
    $evidence = $submission->evidence()->create([
        'storage_key' => 'work-item-evidence/x.jpg',
        'source_type' => 'upload',
        'uploaded_at' => now(),
        'ai_status' => 'mismatch',
    ]);

    $this->actingAs($pic, 'sanctum')
        ->postJson("/api/v1/work-items/{$item->id}/evidence/{$evidence->id}/review", ['status' => 'confirmed'])
        ->assertForbidden();
});
