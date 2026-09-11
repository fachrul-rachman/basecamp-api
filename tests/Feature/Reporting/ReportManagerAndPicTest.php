<?php

use App\Models\Department;
use App\Models\Role;
use App\Models\WorkItem;
use Illuminate\Support\Carbon;

test('a manager can view their own manager report', function () {
    $manager = makeManager(Department::factory()->create());

    $response = $this->actingAs($manager, 'sanctum')->getJson("/api/v1/reports/managers/{$manager->id}");

    $response->assertOk()->assertJsonStructure([
        'data' => ['manager_id', 'findings_handled_count', 'sla', 'unresolved_findings', 'self_handled_findings', 'repeat_patterns'],
    ]);
});

test('director can view any manager report', function () {
    $manager = makeManager(Department::factory()->create());
    $director = makeUserWithRoles([Role::DIRECTOR]);

    $this->actingAs($director, 'sanctum')->getJson("/api/v1/reports/managers/{$manager->id}")->assertOk();
});

test('a manager cannot view another managers report', function () {
    $manager = makeManager(Department::factory()->create());
    $otherManager = makeManager(Department::factory()->create());

    $this->actingAs($otherManager, 'sanctum')->getJson("/api/v1/reports/managers/{$manager->id}")->assertForbidden();
});

test('a pic can view their own pic report', function () {
    $pic = makeUserWithRoles([Role::PIC]);

    $response = $this->actingAs($pic, 'sanctum')->getJson("/api/v1/reports/pics/{$pic->id}");

    $response->assertOk()->assertJsonStructure([
        'data' => ['pic_id', 'work_items', 'finding_count', 'repeat_patterns'],
    ]);
});

test('a manager can view a pic report within their own department', function () {
    $department = Department::factory()->create();
    $manager = makeManager($department);
    $pic = makeUserWithRoles([Role::PIC]);
    $pic->departments()->attach($department->id);
    $pic->load('departments');

    $this->actingAs($manager, 'sanctum')->getJson("/api/v1/reports/pics/{$pic->id}")->assertOk();
});

test('a manager cannot view a pic report outside their own department', function () {
    $department = Department::factory()->create();
    $manager = makeManager($department);
    $pic = makeUserWithRoles([Role::PIC]);
    $pic->departments()->attach(Department::factory()->create()->id);
    $pic->load('departments');

    $this->actingAs($manager, 'sanctum')->getJson("/api/v1/reports/pics/{$pic->id}")->assertForbidden();
});

test('a pic cannot view another pics report', function () {
    $pic = makeUserWithRoles([Role::PIC]);
    $otherPic = makeUserWithRoles([Role::PIC]);

    $this->actingAs($otherPic, 'sanctum')->getJson("/api/v1/reports/pics/{$pic->id}")->assertForbidden();
});

test('a pic report breaks out excused work items separately from the other buckets', function () {
    [, $checklist] = makeTaskWithChecklist();
    $pic = makeUserWithRoles([Role::PIC]);

    WorkItem::create([
        'task_id' => $checklist->task_id,
        'task_checklist_id' => $checklist->id,
        'responsible_department_id' => $checklist->target_department_id,
        'assignee_id' => $pic->id,
        'operational_date' => now()->toDateString(),
        'available_at' => Carbon::now(),
        'deadline_at' => Carbon::now()->addHour(),
        'failure_at' => Carbon::now()->addHours(2),
        'execution_status' => WorkItem::EXECUTION_CANCELLED,
        'compliance_status' => WorkItem::COMPLIANCE_NOT_APPLICABLE,
    ]);

    $response = $this->actingAs($pic, 'sanctum')->getJson("/api/v1/reports/pics/{$pic->id}");

    $response->assertOk()->assertJsonPath('data.work_items.not_applicable', 1);
});
