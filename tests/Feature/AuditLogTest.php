<?php

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Role;

test('creating a user writes an audit log entry', function () {
    $admin = makeUserWithRoles([Role::ADMIN]);

    $this->actingAs($admin, 'sanctum')->postJson('/api/v1/users', [
        'name' => 'Audited User',
        'email' => 'audited@example.com',
        'password' => 'password123',
    ])->assertCreated();

    expect(
        AuditLog::where('action', 'user.created')->where('actor_id', $admin->id)->exists()
    )->toBeTrue();
});

test('deactivating a department writes an audit log entry', function () {
    $admin = makeUserWithRoles([Role::ADMIN]);
    $department = Department::factory()->create();

    $this->actingAs($admin, 'sanctum')->deleteJson("/api/v1/departments/{$department->id}")->assertOk();

    expect(
        AuditLog::where('action', 'department.deactivated')->where('entity_id', $department->id)->exists()
    )->toBeTrue();
});
