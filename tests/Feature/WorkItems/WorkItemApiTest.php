<?php

use App\Models\Department;
use App\Models\Role;
use App\Models\WorkItem;

test('a pic sees only their own assigned work items', function () {
    [, $checklistA] = makeTaskWithChecklist();
    [, $checklistB] = makeTaskWithChecklist();
    $pic = makeUserWithRoles([Role::PIC]);
    $otherPic = makeUserWithRoles([Role::PIC]);

    $mine = WorkItem::create([
        'task_id' => $checklistA->task_id,
        'task_checklist_id' => $checklistA->id,
        'responsible_department_id' => $checklistA->target_department_id,
        'assignee_id' => $pic->id,
        'operational_date' => now()->toDateString(),
        'available_at' => now()->subHour(),
        'deadline_at' => now()->addHours(2),
        'failure_at' => now()->addHours(3),
    ]);

    WorkItem::create([
        'task_id' => $checklistB->task_id,
        'task_checklist_id' => $checklistB->id,
        'responsible_department_id' => $checklistB->target_department_id,
        'assignee_id' => $otherPic->id,
        'operational_date' => now()->toDateString(),
        'available_at' => now()->subHour(),
        'deadline_at' => now()->addHours(2),
        'failure_at' => now()->addHours(3),
    ]);

    $response = $this->actingAs($pic, 'sanctum')->getJson('/api/v1/work-items');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');
    expect($ids->all())->toBe([$mine->id]);
});

test('a manager sees work items in their responsible department', function () {
    [, $checklist, $department] = makeTaskWithChecklist();
    $manager = makeManager($department);

    WorkItem::create([
        'task_id' => $checklist->task_id,
        'task_checklist_id' => $checklist->id,
        'responsible_department_id' => $checklist->target_department_id,
        'operational_date' => now()->toDateString(),
        'available_at' => now()->subHour(),
        'deadline_at' => now()->addHours(2),
        'failure_at' => now()->addHours(3),
    ]);

    $response = $this->actingAs($manager, 'sanctum')->getJson('/api/v1/work-items');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});

test('an unrelated manager cannot view a specific work item', function () {
    [, $checklist] = makeTaskWithChecklist();
    $unrelatedManager = makeManager(Department::factory()->create());

    $item = WorkItem::create([
        'task_id' => $checklist->task_id,
        'task_checklist_id' => $checklist->id,
        'responsible_department_id' => $checklist->target_department_id,
        'operational_date' => now()->toDateString(),
        'available_at' => now()->subHour(),
        'deadline_at' => now()->addHours(2),
        'failure_at' => now()->addHours(3),
    ]);

    $this->actingAs($unrelatedManager, 'sanctum')->getJson("/api/v1/work-items/{$item->id}")->assertForbidden();
});

test('tomorrow work items cannot be submitted yet', function () {
    [, $checklist] = makeTaskWithChecklist();
    $pic = makeUserWithRoles([Role::PIC]);

    $tomorrowItem = WorkItem::create([
        'task_id' => $checklist->task_id,
        'task_checklist_id' => $checklist->id,
        'responsible_department_id' => $checklist->target_department_id,
        'assignee_id' => $pic->id,
        'operational_date' => now()->addDay()->toDateString(),
        'available_at' => now()->addDay()->setTime(8, 0),
        'deadline_at' => now()->addDay()->setTime(17, 0),
        'failure_at' => now()->addDays(2)->startOfDay(),
    ]);

    $response = $this->actingAs($pic, 'sanctum')->getJson("/api/v1/work-items/{$tomorrowItem->id}");

    $response->assertOk()
        ->assertJsonPath('data.date_bucket', 'tomorrow')
        ->assertJsonPath('data.can_submit', false);
});

test('a today work item within its window can be submitted', function () {
    [, $checklist] = makeTaskWithChecklist();
    $pic = makeUserWithRoles([Role::PIC]);

    $todayItem = WorkItem::create([
        'task_id' => $checklist->task_id,
        'task_checklist_id' => $checklist->id,
        'responsible_department_id' => $checklist->target_department_id,
        'assignee_id' => $pic->id,
        'operational_date' => now()->toDateString(),
        'available_at' => now()->subHour(),
        'deadline_at' => now()->addHours(2),
        'failure_at' => now()->addHours(3),
    ]);

    $response = $this->actingAs($pic, 'sanctum')->getJson("/api/v1/work-items/{$todayItem->id}");

    $response->assertOk()
        ->assertJsonPath('data.date_bucket', 'today')
        ->assertJsonPath('data.can_submit', true)
        ->assertJsonPath('data.is_late', false);
});

test('a work item past its deadline but before failure is marked late', function () {
    [, $checklist] = makeTaskWithChecklist();
    $pic = makeUserWithRoles([Role::PIC]);

    $lateItem = WorkItem::create([
        'task_id' => $checklist->task_id,
        'task_checklist_id' => $checklist->id,
        'responsible_department_id' => $checklist->target_department_id,
        'assignee_id' => $pic->id,
        'operational_date' => now()->toDateString(),
        'available_at' => now()->subHours(3),
        'deadline_at' => now()->subHour(),
        'failure_at' => now()->addHour(),
    ]);

    $response = $this->actingAs($pic, 'sanctum')->getJson("/api/v1/work-items/{$lateItem->id}");

    $response->assertOk()
        ->assertJsonPath('data.is_late', true)
        ->assertJsonPath('data.can_submit', true);
});

test('work-items/today includes a period-based item currently inside its window', function () {
    [, $checklist] = makeTaskWithChecklist([
        'schedule_type' => 'weekly_quota',
        'schedule_config' => ['start_time' => '08:00', 'end_time' => '09:00', 'period' => 'week', 'target_count' => 1],
    ]);
    $pic = makeUserWithRoles([Role::PIC]);

    $item = WorkItem::create([
        'task_id' => $checklist->task_id,
        'task_checklist_id' => $checklist->id,
        'responsible_department_id' => $checklist->target_department_id,
        'assignee_id' => $pic->id,
        'period_start' => now()->startOfWeek(),
        'period_end' => now()->endOfWeek(),
        'available_at' => now()->startOfWeek(),
        'deadline_at' => now()->endOfWeek(),
        'failure_at' => now()->endOfWeek(),
    ]);

    $response = $this->actingAs($pic, 'sanctum')->getJson('/api/v1/work-items/today');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');
    expect($ids->all())->toBe([$item->id]);
});
