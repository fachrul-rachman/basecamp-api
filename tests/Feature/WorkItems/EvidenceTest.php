<?php

use App\Models\Evidence;
use App\Models\Role;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

test('a pic can upload evidence to their assigned work item', function () {
    [$item, $pic] = makeAvailableWorkItem();

    $response = $this->actingAs($pic, 'sanctum')->post("/api/v1/work-items/{$item->id}/evidence", [
        'file' => UploadedFile::fake()->image('proof.jpg'),
        'source_type' => Evidence::SOURCE_UPLOAD,
    ]);

    $response->assertCreated();
    $evidence = Evidence::first();
    Storage::disk('public')->assertExists($evidence->storage_key);
    expect($evidence->submission->work_item_id)->toBe($item->id);
});

test('camera evidence is rejected when the checklist disallows it', function () {
    [$item, $pic] = makeAvailableWorkItem(['allow_camera' => false]);

    $response = $this->actingAs($pic, 'sanctum')->post("/api/v1/work-items/{$item->id}/evidence", [
        'file' => UploadedFile::fake()->image('proof.jpg'),
        'source_type' => Evidence::SOURCE_CAMERA,
    ]);

    $response->assertStatus(422);
});

test('a pic can delete their own evidence before lock', function () {
    [$item, $pic] = makeAvailableWorkItem();

    $upload = $this->actingAs($pic, 'sanctum')->post("/api/v1/work-items/{$item->id}/evidence", [
        'file' => UploadedFile::fake()->image('proof.jpg'),
        'source_type' => Evidence::SOURCE_UPLOAD,
    ]);
    $evidenceId = $upload->json('data.id');

    $this->actingAs($pic, 'sanctum')->deleteJson("/api/v1/work-items/{$item->id}/evidence/{$evidenceId}")
        ->assertOk();

    expect(Evidence::find($evidenceId))->toBeNull();
});

test('evidence cannot be deleted once the work item is locked', function () {
    [$item, $pic] = makeAvailableWorkItem();

    $upload = $this->actingAs($pic, 'sanctum')->post("/api/v1/work-items/{$item->id}/evidence", [
        'file' => UploadedFile::fake()->image('proof.jpg'),
        'source_type' => Evidence::SOURCE_UPLOAD,
    ]);
    $evidenceId = $upload->json('data.id');

    $item->update(['locked_at' => now()]);

    $this->actingAs($pic, 'sanctum')->deleteJson("/api/v1/work-items/{$item->id}/evidence/{$evidenceId}")
        ->assertStatus(422);
});

test('an unrelated user cannot upload evidence', function () {
    [$item] = makeAvailableWorkItem();
    $otherPic = makeUserWithRoles([Role::PIC]);

    $this->actingAs($otherPic, 'sanctum')->post("/api/v1/work-items/{$item->id}/evidence", [
        'file' => UploadedFile::fake()->image('proof.jpg'),
        'source_type' => Evidence::SOURCE_UPLOAD,
    ])->assertForbidden();
});
