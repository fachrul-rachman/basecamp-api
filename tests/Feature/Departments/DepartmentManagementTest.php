<?php

use App\Models\Department;
use App\Models\Role;
use App\Models\User;

test('admin can create, update, and deactivate a department', function () {
    $admin = makeUserWithRoles([Role::ADMIN]);

    $create = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/departments', [
        'code' => 'FIN',
        'name' => 'Finance',
    ]);
    $create->assertCreated();
    $departmentId = $create->json('data.id');

    $update = $this->actingAs($admin, 'sanctum')->patchJson("/api/v1/departments/{$departmentId}", [
        'name' => 'Finance & Accounting',
    ]);
    $update->assertOk()->assertJsonPath('data.name', 'Finance & Accounting');

    $this->actingAs($admin, 'sanctum')->deleteJson("/api/v1/departments/{$departmentId}")->assertOk();

    expect(Department::find($departmentId)->is_active)->toBeFalse();
});

test('non-admin cannot create a department', function () {
    $manager = makeUserWithRoles([Role::MANAGER]);

    $response = $this->actingAs($manager, 'sanctum')->postJson('/api/v1/departments', [
        'code' => 'X',
        'name' => 'X Dept',
    ]);

    $response->assertForbidden();
});

test('director can view all departments globally', function () {
    Department::factory()->count(3)->create();
    $director = makeUserWithRoles([Role::DIRECTOR]);

    $response = $this->actingAs($director, 'sanctum')->getJson('/api/v1/departments');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(3);
});

test('pic only sees their own department, not others', function () {
    $ownDept = Department::factory()->create();
    Department::factory()->count(2)->create();

    $pic = makeUserWithRoles([Role::PIC]);
    $pic->departments()->attach($ownDept->id);

    $response = $this->actingAs($pic, 'sanctum')->getJson('/api/v1/departments');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');
    expect($ids->all())->toEqual([$ownDept->id]);
});

test('admin can update department members with a primary flag', function () {
    $admin = makeUserWithRoles([Role::ADMIN]);
    $department = Department::factory()->create();
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $response = $this->actingAs($admin, 'sanctum')->putJson("/api/v1/departments/{$department->id}/members", [
        'members' => [
            ['user_id' => $userA->id, 'is_primary' => true],
            ['user_id' => $userB->id, 'is_primary' => false],
        ],
    ]);

    $response->assertOk();
    expect($department->users()->count())->toBe(2);
    expect($department->users()->wherePivot('is_primary', true)->first()->id)->toBe($userA->id);
});

test('manager cannot update department members', function () {
    $manager = makeUserWithRoles([Role::MANAGER]);
    $department = Department::factory()->create();
    $manager->departments()->attach($department->id, ['is_primary' => true]);
    $userA = User::factory()->create();

    $response = $this->actingAs($manager, 'sanctum')->putJson("/api/v1/departments/{$department->id}/members", [
        'members' => [
            ['user_id' => $userA->id],
        ],
    ]);

    $response->assertForbidden();
});
