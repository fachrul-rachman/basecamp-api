<?php

use App\Models\Department;
use App\Models\Role;

test('director can view the company report', function () {
    $director = makeUserWithRoles([Role::DIRECTOR]);

    $response = $this->actingAs($director, 'sanctum')->getJson('/api/v1/reports/company');

    $response->assertOk()
        ->assertJsonStructure(['data' => ['date_from', 'date_to', 'work_items', 'findings', 'department_requests', 'sla']]);
});

test('iso can view the company report', function () {
    $iso = makeUserWithRoles([Role::ISO]);

    $this->actingAs($iso, 'sanctum')->getJson('/api/v1/reports/company')->assertOk();
});

test('admin cannot view the company report', function () {
    $admin = makeUserWithRoles([Role::ADMIN]);

    $this->actingAs($admin, 'sanctum')->getJson('/api/v1/reports/company')->assertForbidden();
});

test('a manager cannot view the company report', function () {
    $manager = makeManager(Department::factory()->create());

    $this->actingAs($manager, 'sanctum')->getJson('/api/v1/reports/company')->assertForbidden();
});

test('the company report never exposes a numeric score field', function () {
    $director = makeUserWithRoles([Role::DIRECTOR]);

    $response = $this->actingAs($director, 'sanctum')->getJson('/api/v1/reports/company');

    expect($response->json('data'))->not->toHaveKey('score');
});

test('iso can view the iso report with expected shape', function () {
    $iso = makeUserWithRoles([Role::ISO]);

    $response = $this->actingAs($iso, 'sanctum')->getJson('/api/v1/reports/iso');

    $response->assertOk()->assertJsonStructure([
        'data' => ['open_escalations', 'iso_sla', 'manual_audits', 'unresolved_aged_cases'],
    ]);
});

test('a manager cannot view the iso report', function () {
    $manager = makeManager(Department::factory()->create());

    $this->actingAs($manager, 'sanctum')->getJson('/api/v1/reports/iso')->assertForbidden();
});
