<?php

use App\Models\Department;
use App\Models\Role;
use App\Models\User;

test('admin can create a user with roles and a primary department', function () {
    $admin = makeUserWithRoles([Role::ADMIN]);
    Role::firstOrCreate(['code' => Role::PIC], ['name' => 'PIC']);
    $department = Department::factory()->create();

    $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/users', [
        'name' => 'New PIC',
        'email' => 'new.pic@example.com',
        'password' => 'password123',
        'role_codes' => [Role::PIC],
        'department_ids' => [$department->id],
        'primary_department_id' => $department->id,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.email', 'new.pic@example.com')
        ->assertJsonPath('data.roles.0.code', Role::PIC)
        ->assertJsonPath('data.departments.0.is_primary', true);
});

test('superadmin can create a user', function () {
    $superadmin = makeUserWithRoles([Role::SUPERADMIN]);
    Role::firstOrCreate(['code' => Role::ADMIN], ['name' => 'Admin']);

    $response = $this->actingAs($superadmin, 'sanctum')->postJson('/api/v1/users', [
        'name' => 'New Admin',
        'email' => 'new.admin@example.com',
        'password' => 'password123',
        'role_codes' => [Role::ADMIN],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.email', 'new.admin@example.com')
        ->assertJsonPath('data.roles.0.code', Role::ADMIN);
});

test('superadmin can list and view any user', function () {
    $superadmin = makeUserWithRoles([Role::SUPERADMIN]);
    $target = makeUserWithRoles([Role::ADMIN]);

    $this->actingAs($superadmin, 'sanctum')->getJson('/api/v1/users')->assertOk();
    $this->actingAs($superadmin, 'sanctum')->getJson("/api/v1/users/{$target->id}")->assertOk();
});

test('superadmin deactivates a user', function () {
    $superadmin = makeUserWithRoles([Role::SUPERADMIN]);
    $target = makeUserWithRoles([Role::ADMIN]);

    $response = $this->actingAs($superadmin, 'sanctum')->deleteJson("/api/v1/users/{$target->id}");

    $response->assertOk();
    expect($target->fresh()->is_active)->toBeFalse();
});

test('non-admin cannot create a user', function () {
    $manager = makeUserWithRoles([Role::MANAGER]);

    $response = $this->actingAs($manager, 'sanctum')->postJson('/api/v1/users', [
        'name' => 'X',
        'email' => 'x@example.com',
        'password' => 'password123',
    ]);

    $response->assertForbidden();
});

test('admin cannot assign the admin role to a user', function () {
    $admin = makeUserWithRoles([Role::ADMIN]);
    $target = makeUserWithRoles([Role::PIC]);

    $response = $this->actingAs($admin, 'sanctum')->patchJson("/api/v1/users/{$target->id}", [
        'role_codes' => [Role::PIC, Role::ADMIN],
    ]);

    $response->assertForbidden();
    expect($target->fresh()->hasRole(Role::ADMIN))->toBeFalse();
});

test('superadmin can assign the admin role to a user', function () {
    $superadmin = makeUserWithRoles([Role::SUPERADMIN]);
    Role::firstOrCreate(['code' => Role::ADMIN], ['name' => 'Admin']);
    $target = makeUserWithRoles([Role::PIC]);

    $response = $this->actingAs($superadmin, 'sanctum')->patchJson("/api/v1/users/{$target->id}", [
        'role_codes' => [Role::PIC, Role::ADMIN],
    ]);

    $response->assertOk();
    expect($target->fresh()->hasRole(Role::ADMIN))->toBeTrue();
});

test('a user can hold multiple roles simultaneously', function () {
    $user = makeUserWithRoles([Role::MANAGER, Role::PIC]);

    expect($user->hasRole(Role::MANAGER))->toBeTrue()
        ->and($user->hasRole(Role::PIC))->toBeTrue();
});

test('manager can only list users within their own department', function () {
    $deptA = Department::factory()->create();
    $deptB = Department::factory()->create();

    $manager = makeUserWithRoles([Role::MANAGER]);
    $manager->departments()->attach($deptA->id, ['is_primary' => true]);
    $manager->load('departments');

    $userInDeptA = makeUserWithRoles([Role::PIC]);
    $userInDeptA->departments()->attach($deptA->id);

    $userInDeptB = makeUserWithRoles([Role::PIC]);
    $userInDeptB->departments()->attach($deptB->id);

    $response = $this->actingAs($manager, 'sanctum')->getJson('/api/v1/users');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');

    expect($ids)->toContain($manager->id, $userInDeptA->id)
        ->and($ids)->not->toContain($userInDeptB->id);
});

test('admin deactivates a user instead of deleting the row', function () {
    $admin = makeUserWithRoles([Role::ADMIN]);
    $target = User::factory()->create();

    $response = $this->actingAs($admin, 'sanctum')->deleteJson("/api/v1/users/{$target->id}");

    $response->assertOk();
    expect($target->fresh())->not->toBeNull();
    expect($target->fresh()->is_active)->toBeFalse();
});

test('pic cannot view a user outside their department', function () {
    $deptA = Department::factory()->create();
    $deptB = Department::factory()->create();

    $pic = makeUserWithRoles([Role::PIC]);
    $pic->departments()->attach($deptA->id);

    $otherUser = makeUserWithRoles([Role::PIC]);
    $otherUser->departments()->attach($deptB->id);

    $response = $this->actingAs($pic, 'sanctum')->getJson("/api/v1/users/{$otherUser->id}");

    $response->assertForbidden();
});
