<?php

use App\Models\Department;
use App\Models\Role;
use App\Models\WorkItem;
use App\Models\WorkItemAssignment;

test('the responsible department manager can assign a pic to an unassigned work item', function () {
    [, $checklist, $department] = makeTaskWithChecklist();
    $manager = makeManager($department);
    $pic = makeUserWithRoles([Role::PIC]);
    $pic->departments()->attach($department->id);

    $item = WorkItem::create([
        'task_id' => $checklist->task_id,
        'task_checklist_id' => $checklist->id,
        'responsible_department_id' => $checklist->target_department_id,
        'operational_date' => now()->toDateString(),
        'available_at' => now()->subHour(),
        'deadline_at' => now()->addHours(2),
        'failure_at' => now()->addHours(3),
    ]);

    $response = $this->actingAs($manager, 'sanctum')->postJson("/api/v1/work-items/{$item->id}/reassign", [
        'pic_id' => $pic->id,
    ]);

    $response->assertOk()->assertJsonPath('data.assignee_id', $pic->id);
    $history = WorkItemAssignment::first();
    expect($history->from_user_id)->toBeNull();
    expect($history->to_user_id)->toBe($pic->id);
});

test('reassigning again preserves the previous assignment as history', function () {
    [, $checklist, $department] = makeTaskWithChecklist();
    $manager = makeManager($department);
    $firstPic = makeUserWithRoles([Role::PIC]);
    $firstPic->departments()->attach($department->id);
    $secondPic = makeUserWithRoles([Role::PIC]);
    $secondPic->departments()->attach($department->id);

    $item = WorkItem::create([
        'task_id' => $checklist->task_id,
        'task_checklist_id' => $checklist->id,
        'responsible_department_id' => $checklist->target_department_id,
        'assignee_id' => $firstPic->id,
        'operational_date' => now()->toDateString(),
        'available_at' => now()->subHour(),
        'deadline_at' => now()->addHours(2),
        'failure_at' => now()->addHours(3),
    ]);

    $this->actingAs($manager, 'sanctum')->postJson("/api/v1/work-items/{$item->id}/reassign", [
        'pic_id' => $secondPic->id,
        'reason' => 'Original PIC is on leave',
    ])->assertOk();

    expect(WorkItemAssignment::count())->toBe(1);
    $history = WorkItemAssignment::first();
    expect($history->from_user_id)->toBe($firstPic->id);
    expect($history->to_user_id)->toBe($secondPic->id);
    expect($item->fresh()->assignee_id)->toBe($secondPic->id);
});

test('a pic cannot reassign work items', function () {
    [, $checklist, $department] = makeTaskWithChecklist();
    $pic = makeUserWithRoles([Role::PIC]);
    $otherPic = makeUserWithRoles([Role::PIC]);
    $otherPic->departments()->attach($department->id);

    $item = WorkItem::create([
        'task_id' => $checklist->task_id,
        'task_checklist_id' => $checklist->id,
        'responsible_department_id' => $checklist->target_department_id,
        'operational_date' => now()->toDateString(),
        'available_at' => now()->subHour(),
        'deadline_at' => now()->addHours(2),
        'failure_at' => now()->addHours(3),
    ]);

    $this->actingAs($pic, 'sanctum')->postJson("/api/v1/work-items/{$item->id}/reassign", [
        'pic_id' => $otherPic->id,
    ])->assertForbidden();
});

test('a manager from an unrelated department cannot reassign', function () {
    [, $checklist, $department] = makeTaskWithChecklist();
    $unrelatedManager = makeManager(Department::factory()->create());
    $pic = makeUserWithRoles([Role::PIC]);
    $pic->departments()->attach($department->id);

    $item = WorkItem::create([
        'task_id' => $checklist->task_id,
        'task_checklist_id' => $checklist->id,
        'responsible_department_id' => $checklist->target_department_id,
        'operational_date' => now()->toDateString(),
        'available_at' => now()->subHour(),
        'deadline_at' => now()->addHours(2),
        'failure_at' => now()->addHours(3),
    ]);

    $this->actingAs($unrelatedManager, 'sanctum')->postJson("/api/v1/work-items/{$item->id}/reassign", [
        'pic_id' => $pic->id,
    ])->assertForbidden();
});

test('reassigning to a pic outside the responsible department is rejected', function () {
    [, $checklist, $department] = makeTaskWithChecklist();
    $manager = makeManager($department);
    $wrongDeptPic = makeUserWithRoles([Role::PIC]);
    $wrongDeptPic->departments()->attach(Department::factory()->create()->id);

    $item = WorkItem::create([
        'task_id' => $checklist->task_id,
        'task_checklist_id' => $checklist->id,
        'responsible_department_id' => $checklist->target_department_id,
        'operational_date' => now()->toDateString(),
        'available_at' => now()->subHour(),
        'deadline_at' => now()->addHours(2),
        'failure_at' => now()->addHours(3),
    ]);

    $this->actingAs($manager, 'sanctum')->postJson("/api/v1/work-items/{$item->id}/reassign", [
        'pic_id' => $wrongDeptPic->id,
    ])->assertStatus(422);
});

test('a locked work item cannot be reassigned', function () {
    [, $checklist, $department] = makeTaskWithChecklist();
    $manager = makeManager($department);
    $pic = makeUserWithRoles([Role::PIC]);
    $pic->departments()->attach($department->id);

    $item = WorkItem::create([
        'task_id' => $checklist->task_id,
        'task_checklist_id' => $checklist->id,
        'responsible_department_id' => $checklist->target_department_id,
        'operational_date' => now()->toDateString(),
        'available_at' => now()->subHour(),
        'deadline_at' => now()->addHours(2),
        'failure_at' => now()->addHours(3),
        'locked_at' => now(),
        'execution_status' => WorkItem::EXECUTION_FAILED,
        'compliance_status' => WorkItem::COMPLIANCE_FAILED,
    ]);

    $this->actingAs($manager, 'sanctum')->postJson("/api/v1/work-items/{$item->id}/reassign", [
        'pic_id' => $pic->id,
    ])->assertStatus(422);
});
