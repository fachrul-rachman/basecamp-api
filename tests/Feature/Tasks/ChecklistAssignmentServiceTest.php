<?php

use App\Models\Department;
use App\Models\DepartmentRequest;
use App\Models\Role;
use App\Models\WorkItem;
use App\Services\ChecklistAssignmentService;
use Illuminate\Validation\ValidationException;

test('setting a default assignee updates the checklist and unfinished work items, skipping locked ones', function () {
    [, $checklist, $department] = makeTaskWithChecklist();
    $manager = makeManager($department);
    $pic = makeUserWithRoles([Role::PIC]);
    $pic->departments()->attach($department->id);
    $pic->load('departments');

    $unfinished = WorkItem::create([
        'task_id' => $checklist->task_id,
        'task_checklist_id' => $checklist->id,
        'responsible_department_id' => $department->id,
        'operational_date' => now()->toDateString(),
        'available_at' => now(),
        'deadline_at' => now()->addHours(2),
        'failure_at' => now()->addHours(3),
    ]);
    $locked = WorkItem::create([
        'task_id' => $checklist->task_id,
        'task_checklist_id' => $checklist->id,
        'responsible_department_id' => $department->id,
        'operational_date' => now()->subDay()->toDateString(),
        'available_at' => now()->subDay(),
        'deadline_at' => now()->subHours(2),
        'failure_at' => now()->subHour(),
        'execution_status' => WorkItem::EXECUTION_COMPLETED,
        'locked_at' => now(),
    ]);

    app(ChecklistAssignmentService::class)->setDefaultAssignee($manager, $checklist, $pic);

    expect($checklist->fresh()->default_assignee_id)->toBe($pic->id);
    expect($unfinished->fresh()->assignee_id)->toBe($pic->id);
    expect($locked->fresh()->assignee_id)->toBeNull();
});

test('setting a default assignee closes overlapping future temporary overrides', function () {
    [, $checklist, $department] = makeTaskWithChecklist();
    $manager = makeManager($department);
    $pic = makeUserWithRoles([Role::PIC]);
    $pic->departments()->attach($department->id);
    $pic->load('departments');
    $other = makeUserWithRoles([Role::PIC]);
    $other->departments()->attach($department->id);

    $checklist->assigneeOverrides()->create([
        'assignee_id' => $other->id,
        'starts_at' => now()->addDay()->toDateString(),
        'ends_at' => now()->addDays(3)->toDateString(),
        'created_by' => $manager->id,
    ]);

    app(ChecklistAssignmentService::class)->setDefaultAssignee($manager, $checklist, $pic);

    expect($checklist->assigneeOverrides()->count())->toBe(0);
});

test('setting a default assignee rejects a weekly_quota checklist', function () {
    [, $checklist, $department] = makeTaskWithChecklist();
    $checklist->update(['schedule_type' => 'weekly_quota', 'schedule_config' => ['period' => 'week', 'target_count' => 3]]);
    $manager = makeManager($department);
    $pic = makeUserWithRoles([Role::PIC]);
    $pic->departments()->attach($department->id);

    expect(fn () => app(ChecklistAssignmentService::class)->setDefaultAssignee($manager, $checklist, $pic))
        ->toThrow(ValidationException::class);
});

test('setting a default assignee rejects a cross-department checklist', function () {
    [$task, $checklist, $department] = makeTaskWithChecklist();
    $otherDepartment = Department::factory()->create();
    $checklist->update(['target_department_id' => $otherDepartment->id]);
    DepartmentRequest::create([
        'task_id' => $task->id,
        'task_checklist_id' => $checklist->id,
        'owner_department_id' => $department->id,
        'target_department_id' => $otherDepartment->id,
        'status' => DepartmentRequest::STATUS_PENDING,
    ]);
    $manager = makeManager($department);
    $pic = makeUserWithRoles([Role::PIC]);
    $pic->departments()->attach($otherDepartment->id);

    expect(fn () => app(ChecklistAssignmentService::class)->setDefaultAssignee($manager, $checklist, $pic))
        ->toThrow(ValidationException::class);
});

test('setting a default assignee rejects a pic outside the target department', function () {
    [, $checklist, $department] = makeTaskWithChecklist();
    $manager = makeManager($department);
    $outsider = makeUserWithRoles([Role::PIC]);

    expect(fn () => app(ChecklistAssignmentService::class)->setDefaultAssignee($manager, $checklist, $outsider))
        ->toThrow(ValidationException::class);
});

test('a temporary override only reassigns work items inside its date range', function () {
    [, $checklist, $department] = makeTaskWithChecklist();
    $manager = makeManager($department);
    $pic = makeUserWithRoles([Role::PIC]);
    $pic->departments()->attach($department->id);
    $pic->load('departments');

    $inside = WorkItem::create([
        'task_id' => $checklist->task_id, 'task_checklist_id' => $checklist->id,
        'responsible_department_id' => $department->id,
        'operational_date' => now()->addDay()->toDateString(),
        'available_at' => now(), 'deadline_at' => now()->addHours(2), 'failure_at' => now()->addHours(3),
    ]);
    $outside = WorkItem::create([
        'task_id' => $checklist->task_id, 'task_checklist_id' => $checklist->id,
        'responsible_department_id' => $department->id,
        'operational_date' => now()->addDays(10)->toDateString(),
        'available_at' => now(), 'deadline_at' => now()->addHours(2), 'failure_at' => now()->addHours(3),
    ]);

    app(ChecklistAssignmentService::class)->createTemporaryOverride(
        $manager, $checklist, $pic, now(), now()->addDays(2)
    );

    expect($inside->fresh()->assignee_id)->toBe($pic->id);
    expect($outside->fresh()->assignee_id)->toBeNull();
});

test('an overlapping temporary override is rejected', function () {
    [, $checklist, $department] = makeTaskWithChecklist();
    $manager = makeManager($department);
    $pic = makeUserWithRoles([Role::PIC]);
    $pic->departments()->attach($department->id);
    $pic->load('departments');

    app(ChecklistAssignmentService::class)->createTemporaryOverride($manager, $checklist, $pic, now(), now()->addDays(2));

    expect(fn () => app(ChecklistAssignmentService::class)->createTemporaryOverride($manager, $checklist, $pic, now()->addDay(), now()->addDays(3)))
        ->toThrow(ValidationException::class);
});

test('resolveEffectiveAssignee prefers an active override over the default', function () {
    [, $checklist, $department] = makeTaskWithChecklist();
    $default = makeUserWithRoles([Role::PIC]);
    $override = makeUserWithRoles([Role::PIC]);
    $checklist->update(['default_assignee_id' => $default->id]);
    $checklist->assigneeOverrides()->create([
        'assignee_id' => $override->id,
        'starts_at' => now()->toDateString(),
        'ends_at' => now()->addDay()->toDateString(),
        'created_by' => $default->id,
    ]);

    $resolved = app(ChecklistAssignmentService::class)->resolveEffectiveAssignee($checklist, now());

    expect($resolved)->toBe($override->id);
});

test('resolveEffectiveAssignee falls back to the default outside the override window', function () {
    [, $checklist] = makeTaskWithChecklist();
    $default = makeUserWithRoles([Role::PIC]);
    $checklist->update(['default_assignee_id' => $default->id]);

    $resolved = app(ChecklistAssignmentService::class)->resolveEffectiveAssignee($checklist, now());

    expect($resolved)->toBe($default->id);
});

test('resolveEffectiveAssignee always returns null for weekly_quota', function () {
    [, $checklist] = makeTaskWithChecklist();
    $checklist->update([
        'schedule_type' => 'weekly_quota',
        'schedule_config' => ['period' => 'week', 'target_count' => 3],
        'default_assignee_id' => makeUserWithRoles([Role::PIC])->id,
    ]);

    expect(app(ChecklistAssignmentService::class)->resolveEffectiveAssignee($checklist, now()))->toBeNull();
});

test('resolveEffectiveAssignee returns the accepted department request pic for cross-department checklists', function () {
    [$task, $checklist, $department] = makeTaskWithChecklist();
    $otherDepartment = Department::factory()->create();
    $checklist->update(['target_department_id' => $otherDepartment->id]);
    $targetPic = makeUserWithRoles([Role::PIC]);
    DepartmentRequest::create([
        'task_id' => $task->id,
        'task_checklist_id' => $checklist->id,
        'owner_department_id' => $department->id,
        'target_department_id' => $otherDepartment->id,
        'status' => DepartmentRequest::STATUS_ASSIGNED,
        'assigned_pic_id' => $targetPic->id,
    ]);
    $checklist->load('departmentRequests');

    expect(app(ChecklistAssignmentService::class)->resolveEffectiveAssignee($checklist, now()))->toBe($targetPic->id);
});
