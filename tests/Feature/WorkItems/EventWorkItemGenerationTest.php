<?php

use App\Models\Department;
use App\Models\Role;
use App\Models\WorkItem;

test('creating a same-department event checklist immediately generates an unassigned work item', function () {
    $department = Department::factory()->create();
    $manager = makeManager($department);

    $response = $this->actingAs($manager, 'sanctum')->postJson('/api/v1/tasks', [
        'owner_department_id' => $department->id,
        'title' => 'Urgent Spill Response',
        'starts_at' => now()->toIso8601String(),
        'checklists' => [
            [
                'title' => 'Clean up the spill',
                'schedule_type' => 'event',
                'schedule_config' => ['response_window_hours' => 4],
            ],
        ],
    ]);

    $response->assertCreated();
    $item = WorkItem::first();
    expect($item)->not->toBeNull();
    expect($item->assignee_id)->toBeNull();
    expect($item->department_request_id)->toBeNull();
    expect((int) $item->available_at->diffInHours($item->deadline_at))->toBe(4);
});

test('assigning a cross-department event department request generates a work item for the assigned pic', function () {
    ['request' => $departmentRequest, 'targetManager' => $targetManager, 'targetDepartment' => $targetDepartment] = createCrossDepartmentRequest();
    // createCrossDepartmentRequest uses schedule_type one_time by default;
    // switch the checklist to event to exercise this path.
    $departmentRequest->taskChecklist->update([
        'schedule_type' => 'event',
        'schedule_config' => ['response_window_hours' => 8],
    ]);

    $pic = makeUserWithRoles([Role::PIC]);
    $pic->departments()->attach($targetDepartment->id);

    $this->actingAs($targetManager, 'sanctum')
        ->postJson("/api/v1/department-requests/{$departmentRequest->id}/assign", ['pic_id' => $pic->id])
        ->assertOk();

    $item = WorkItem::first();
    expect($item)->not->toBeNull();
    expect($item->assignee_id)->toBe($pic->id);
    expect($item->department_request_id)->toBe($departmentRequest->id);
});
