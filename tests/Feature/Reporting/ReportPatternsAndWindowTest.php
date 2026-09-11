<?php

use App\Models\Department;
use App\Models\Role;

test('director can query patterns across all departments', function () {
    $director = makeUserWithRoles([Role::DIRECTOR]);

    $response = $this->actingAs($director, 'sanctum')->getJson('/api/v1/reports/patterns');

    $response->assertOk()->assertJsonStructure([
        'data' => ['by_pic_and_checklist', 'by_pic_and_type', 'by_checklist', 'by_manager_sla_breach', 'by_department'],
    ]);
});

test('a manager cannot filter patterns to a department they do not manage', function () {
    $manager = makeManager(Department::factory()->create());
    $otherDepartment = Department::factory()->create();

    $this->actingAs($manager, 'sanctum')
        ->getJson('/api/v1/reports/patterns?department_id='.$otherDepartment->id)
        ->assertForbidden();
});

test('a pic patterns query is always scoped to themselves regardless of filters', function () {
    $pic = makeUserWithRoles([Role::PIC]);
    $otherPic = makeUserWithRoles([Role::PIC]);

    $response = $this->actingAs($pic, 'sanctum')
        ->getJson('/api/v1/reports/patterns?pic_id='.$otherPic->id);

    $response->assertOk();
});

test('the days query parameter controls the report window', function () {
    $director = makeUserWithRoles([Role::DIRECTOR]);

    $response = $this->actingAs($director, 'sanctum')->getJson('/api/v1/reports/company?days=7');

    $response->assertOk();
    $expectedFrom = now()->subDays(7)->startOfDay()->toDateString();
    expect($response->json('data.date_from'))->toBe($expectedFrom);
});

test('explicit date_from and date_to override the days window', function () {
    $director = makeUserWithRoles([Role::DIRECTOR]);

    $response = $this->actingAs($director, 'sanctum')->getJson('/api/v1/reports/company?date_from=2026-01-01&date_to=2026-01-31');

    $response->assertOk()
        ->assertJsonPath('data.date_from', '2026-01-01')
        ->assertJsonPath('data.date_to', '2026-01-31');
});
