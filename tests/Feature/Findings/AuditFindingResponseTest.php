<?php

use App\Models\Department;
use App\Models\Finding;
use App\Models\FindingEvidence;
use App\Models\Role;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('the target manager can respond with notes and evidence', function () {
    Storage::fake('public');

    $iso = makeUserWithRoles([Role::ISO]);
    $department = Department::factory()->create();
    $manager = makeManager($department);

    $create = $this->actingAs($iso, 'sanctum')->postJson('/api/v1/audit-findings', [
        'title' => 'Fire extinguisher expired',
        'target_department_id' => $department->id,
        'target_manager_id' => $manager->id,
        'status' => Finding::STATUS_OPEN,
    ]);
    $findingId = $create->json('data.id');

    $response = $this->actingAs($manager, 'sanctum')->post("/api/v1/audit-findings/{$findingId}/manager-response", [
        'notes' => 'Replaced the extinguisher, photo attached',
        'evidence' => [UploadedFile::fake()->image('proof.jpg')],
    ]);

    $response->assertOk();
    expect(FindingEvidence::where('finding_id', $findingId)->count())->toBe(1);
});

test('an unrelated manager cannot respond to a manual audit finding', function () {
    $iso = makeUserWithRoles([Role::ISO]);
    $department = Department::factory()->create();
    $unrelatedManager = makeManager(Department::factory()->create());

    $create = $this->actingAs($iso, 'sanctum')->postJson('/api/v1/audit-findings', [
        'title' => 'X',
        'target_department_id' => $department->id,
        'status' => Finding::STATUS_OPEN,
    ]);
    $findingId = $create->json('data.id');

    $this->actingAs($unrelatedManager, 'sanctum')->post("/api/v1/audit-findings/{$findingId}/manager-response", [
        'notes' => 'Not my department',
    ])->assertForbidden();
});

test('responding to an automatic finding through the audit-findings route is not found', function () {
    [$finding, $manager] = makeFailedFindingSetup();

    $this->actingAs($manager, 'sanctum')->post("/api/v1/audit-findings/{$finding->id}/manager-response", [
        'notes' => 'Wrong endpoint',
    ])->assertNotFound();
});
