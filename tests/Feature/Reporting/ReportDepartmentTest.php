<?php

use App\Models\Department;
use App\Models\Role;

test('director sees all departments in the summary list', function () {
    Department::factory()->count(2)->create();
    $director = makeUserWithRoles([Role::DIRECTOR]);

    $response = $this->actingAs($director, 'sanctum')->getJson('/api/v1/reports/departments');

    $response->assertOk();
    expect(count($response->json('data')))->toBeGreaterThanOrEqual(2);
});

test('a manager only sees their own department in the summary list', function () {
    $ownDepartment = Department::factory()->create();
    Department::factory()->create();
    $manager = makeManager($ownDepartment);

    $response = $this->actingAs($manager, 'sanctum')->getJson('/api/v1/reports/departments');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('department_id');
    expect($ids->all())->toBe([$ownDepartment->id]);
});

test('a manager can drill down into their own department', function () {
    [, $checklist, $department] = makeTaskWithChecklist();
    $manager = makeManager($department);

    $response = $this->actingAs($manager, 'sanctum')->getJson("/api/v1/reports/departments/{$department->id}");

    $response->assertOk()->assertJsonStructure([
        'data' => ['department_id', 'work_items', 'findings', 'managers', 'pics', 'open_findings'],
    ]);
});

test('a manager cannot drill down into a department they do not manage', function () {
    $ownDepartment = Department::factory()->create();
    $otherDepartment = Department::factory()->create();
    $manager = makeManager($ownDepartment);

    $this->actingAs($manager, 'sanctum')->getJson("/api/v1/reports/departments/{$otherDepartment->id}")
        ->assertForbidden();
});

test('director can drill down into any department', function () {
    $department = Department::factory()->create();
    $director = makeUserWithRoles([Role::DIRECTOR]);

    $this->actingAs($director, 'sanctum')->getJson("/api/v1/reports/departments/{$department->id}")->assertOk();
});
