<?php

use App\Models\Department;
use App\Models\Finding;
use App\Models\Role;

test('iso can create an open manual audit finding with a deadline', function () {
    $iso = makeUserWithRoles([Role::ISO]);
    $department = Department::factory()->create();
    $manager = makeManager($department);

    $response = $this->actingAs($iso, 'sanctum')->postJson('/api/v1/audit-findings', [
        'title' => 'Fire extinguisher expired',
        'description' => 'Observed during walkthrough',
        'target_department_id' => $department->id,
        'target_manager_id' => $manager->id,
        'due_at' => now()->addDays(3)->toIso8601String(),
        'status' => Finding::STATUS_OPEN,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.source_type', Finding::SOURCE_ISO_MANUAL)
        ->assertJsonPath('data.status', Finding::STATUS_OPEN)
        ->assertJsonPath('data.target_user_id', $manager->id);
});

test('iso can create an info finding without a deadline', function () {
    $iso = makeUserWithRoles([Role::ISO]);
    $department = Department::factory()->create();

    $response = $this->actingAs($iso, 'sanctum')->postJson('/api/v1/audit-findings', [
        'title' => 'Minor observation for the record',
        'target_department_id' => $department->id,
        'status' => Finding::STATUS_INFO,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', Finding::STATUS_INFO)
        ->assertJsonPath('data.due_at', null);
});

test('a non-iso cannot create a manual audit finding', function () {
    $department = Department::factory()->create();
    $manager = makeManager($department);

    $this->actingAs($manager, 'sanctum')->postJson('/api/v1/audit-findings', [
        'title' => 'X',
        'target_department_id' => $department->id,
        'status' => Finding::STATUS_OPEN,
    ])->assertForbidden();
});

test('a target manager not belonging to the target department is rejected', function () {
    $iso = makeUserWithRoles([Role::ISO]);
    $department = Department::factory()->create();
    $unrelatedManager = makeManager(Department::factory()->create());

    $response = $this->actingAs($iso, 'sanctum')->postJson('/api/v1/audit-findings', [
        'title' => 'X',
        'target_department_id' => $department->id,
        'target_manager_id' => $unrelatedManager->id,
        'status' => Finding::STATUS_OPEN,
    ]);

    $response->assertStatus(422);
});
