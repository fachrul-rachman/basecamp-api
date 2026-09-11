<?php

use App\Models\Department;
use App\Models\Role;
use App\Models\SlaSetting;

test('iso can set the global manager sla', function () {
    $iso = makeUserWithRoles([Role::ISO]);

    $response = $this->actingAs($iso, 'sanctum')->putJson('/api/v1/sla-settings/global', ['minutes' => 300]);

    $response->assertSuccessful()->assertJsonPath('data.minutes', 300);
});

test('admin cannot set the global manager sla', function () {
    $admin = makeUserWithRoles([Role::ADMIN]);

    $response = $this->actingAs($admin, 'sanctum')->putJson('/api/v1/sla-settings/global', ['minutes' => 300]);

    $response->assertForbidden();
});

test('iso can set a department manager sla override', function () {
    $iso = makeUserWithRoles([Role::ISO]);
    $department = Department::factory()->create();

    $response = $this->actingAs($iso, 'sanctum')->putJson("/api/v1/departments/{$department->id}/sla", ['minutes' => 120]);

    $response->assertSuccessful()->assertJsonPath('data.minutes', 120);
});

test('iso can set an individual manager sla override', function () {
    $iso = makeUserWithRoles([Role::ISO]);
    $manager = makeUserWithRoles([Role::MANAGER]);

    $response = $this->actingAs($iso, 'sanctum')->putJson("/api/v1/users/{$manager->id}/sla", ['minutes' => 60]);

    $response->assertSuccessful()->assertJsonPath('data.minutes', 60);
});

test('admin can set the iso sla', function () {
    $admin = makeUserWithRoles([Role::ADMIN]);

    $response = $this->actingAs($admin, 'sanctum')->putJson('/api/v1/sla-settings/iso', ['minutes' => 600]);

    $response->assertSuccessful()->assertJsonPath('data.minutes', 600);
});

test('iso cannot set its own iso sla', function () {
    $iso = makeUserWithRoles([Role::ISO]);

    $response = $this->actingAs($iso, 'sanctum')->putJson('/api/v1/sla-settings/iso', ['minutes' => 600]);

    $response->assertForbidden();
});

test('admin and iso can read all sla settings, manager cannot', function () {
    SlaSetting::create(['scope_type' => SlaSetting::SCOPE_GLOBAL, 'scope_id' => null, 'minutes' => 240]);

    $admin = makeUserWithRoles([Role::ADMIN]);
    $this->actingAs($admin, 'sanctum')->getJson('/api/v1/sla-settings')->assertOk();

    $iso = makeUserWithRoles([Role::ISO]);
    $this->actingAs($iso, 'sanctum')->getJson('/api/v1/sla-settings')->assertOk();

    $manager = makeUserWithRoles([Role::MANAGER]);
    $this->actingAs($manager, 'sanctum')->getJson('/api/v1/sla-settings')->assertForbidden();
});
