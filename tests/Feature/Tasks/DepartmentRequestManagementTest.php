<?php

use App\Models\Department;
use App\Models\DepartmentRequest;
use App\Models\Role;

test('target department manager can assign a pic from their own department', function () {
    ['request' => $departmentRequest, 'targetDepartment' => $targetDepartment, 'targetManager' => $targetManager] = createCrossDepartmentRequest();

    $pic = makeUserWithRoles([Role::PIC]);
    $pic->departments()->attach($targetDepartment->id);

    $response = $this->actingAs($targetManager, 'sanctum')
        ->postJson("/api/v1/department-requests/{$departmentRequest->id}/assign", ['pic_id' => $pic->id]);

    $response->assertOk()
        ->assertJsonPath('data.status', DepartmentRequest::STATUS_ASSIGNED)
        ->assertJsonPath('data.assigned_pic_id', $pic->id);
});

test('assigning a pic from the wrong department is rejected', function () {
    ['request' => $departmentRequest, 'targetManager' => $targetManager] = createCrossDepartmentRequest();

    $unrelatedDepartment = Department::factory()->create();
    $pic = makeUserWithRoles([Role::PIC]);
    $pic->departments()->attach($unrelatedDepartment->id);

    $response = $this->actingAs($targetManager, 'sanctum')
        ->postJson("/api/v1/department-requests/{$departmentRequest->id}/assign", ['pic_id' => $pic->id]);

    $response->assertStatus(422);
});

test('owner department manager cannot assign a pic to their own outgoing request', function () {
    ['request' => $departmentRequest, 'targetDepartment' => $targetDepartment, 'ownerManager' => $ownerManager] = createCrossDepartmentRequest();

    $pic = makeUserWithRoles([Role::PIC]);
    $pic->departments()->attach($targetDepartment->id);

    $response = $this->actingAs($ownerManager, 'sanctum')
        ->postJson("/api/v1/department-requests/{$departmentRequest->id}/assign", ['pic_id' => $pic->id]);

    $response->assertForbidden();
});

test('target department manager can reject a request with a reason', function () {
    ['request' => $departmentRequest, 'targetManager' => $targetManager] = createCrossDepartmentRequest();

    $response = $this->actingAs($targetManager, 'sanctum')
        ->postJson("/api/v1/department-requests/{$departmentRequest->id}/reject", ['reason' => 'No available staff']);

    $response->assertOk()->assertJsonPath('data.status', DepartmentRequest::STATUS_REJECTED);
});

test('rejecting without a reason is rejected by validation', function () {
    ['request' => $departmentRequest, 'targetManager' => $targetManager] = createCrossDepartmentRequest();

    $this->actingAs($targetManager, 'sanctum')
        ->postJson("/api/v1/department-requests/{$departmentRequest->id}/reject", [])
        ->assertStatus(422);
});

test('the owner manager keeps visibility into a rejected request', function () {
    ['request' => $departmentRequest, 'ownerManager' => $ownerManager, 'targetManager' => $targetManager] = createCrossDepartmentRequest();

    $this->actingAs($targetManager, 'sanctum')
        ->postJson("/api/v1/department-requests/{$departmentRequest->id}/reject", ['reason' => 'No available staff'])
        ->assertOk();

    $this->actingAs($ownerManager, 'sanctum')
        ->getJson("/api/v1/department-requests/{$departmentRequest->id}")
        ->assertOk()
        ->assertJsonPath('data.rejection_reason', 'No available staff');
});

test('an unrelated manager cannot view or act on the request', function () {
    ['request' => $departmentRequest] = createCrossDepartmentRequest();

    $unrelatedDepartment = Department::factory()->create();
    $unrelatedManager = makeManager($unrelatedDepartment);

    $this->actingAs($unrelatedManager, 'sanctum')
        ->getJson("/api/v1/department-requests/{$departmentRequest->id}")
        ->assertForbidden();
});
