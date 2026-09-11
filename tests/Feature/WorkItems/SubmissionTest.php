<?php

use App\Models\Role;
use App\Models\Submission;
use App\Models\WorkItem;

test('assigned pic can save a draft submission without locking it', function () {
    [, $checklist] = makeTaskWithChecklist();
    $pic = makeUserWithRoles([Role::PIC]);
    $item = WorkItem::create([
        'task_id' => $checklist->task_id,
        'task_checklist_id' => $checklist->id,
        'responsible_department_id' => $checklist->target_department_id,
        'assignee_id' => $pic->id,
        'operational_date' => now()->toDateString(),
        'available_at' => now()->subHour(),
        'deadline_at' => now()->addHours(2),
        'failure_at' => now()->addHours(3),
        'required_evidence_count' => 1,
    ]);

    $response = $this->actingAs($pic, 'sanctum')->postJson("/api/v1/work-items/{$item->id}/submission", [
        'notes' => 'Working on it',
    ]);

    $response->assertOk();
    $submission = Submission::first();
    expect($submission->notes)->toBe('Working on it');
    expect($submission->locked_at)->toBeNull();
    expect($item->fresh()->execution_status)->toBe(WorkItem::EXECUTION_IN_PROGRESS);
});

test('finalizing a submission with enough evidence completes it on time', function () {
    [, $checklist] = makeTaskWithChecklist();
    $pic = makeUserWithRoles([Role::PIC]);
    $item = WorkItem::create([
        'task_id' => $checklist->task_id,
        'task_checklist_id' => $checklist->id,
        'responsible_department_id' => $checklist->target_department_id,
        'assignee_id' => $pic->id,
        'operational_date' => now()->toDateString(),
        'available_at' => now()->subHour(),
        'deadline_at' => now()->addHours(2),
        'failure_at' => now()->addHours(3),
        'required_evidence_count' => 0,
    ]);

    $response = $this->actingAs($pic, 'sanctum')->postJson("/api/v1/work-items/{$item->id}/submission", [
        'notes' => 'Done',
        'submit' => true,
    ]);

    $response->assertOk();
    expect($item->fresh()->execution_status)->toBe(WorkItem::EXECUTION_COMPLETED);
    expect($item->fresh()->compliance_status)->toBe(WorkItem::COMPLIANCE_ON_TIME);
    expect(Submission::first()->locked_at)->not->toBeNull();
});

test('finalizing after the deadline but before failure marks it late', function () {
    [, $checklist] = makeTaskWithChecklist();
    $pic = makeUserWithRoles([Role::PIC]);
    $item = WorkItem::create([
        'task_id' => $checklist->task_id,
        'task_checklist_id' => $checklist->id,
        'responsible_department_id' => $checklist->target_department_id,
        'assignee_id' => $pic->id,
        'operational_date' => now()->toDateString(),
        'available_at' => now()->subHours(3),
        'deadline_at' => now()->subHour(),
        'failure_at' => now()->addHour(),
        'required_evidence_count' => 0,
    ]);

    $response = $this->actingAs($pic, 'sanctum')->postJson("/api/v1/work-items/{$item->id}/submission", [
        'submit' => true,
    ]);

    $response->assertOk();
    expect($item->fresh()->compliance_status)->toBe(WorkItem::COMPLIANCE_LATE);
});

test('finalizing without enough evidence is rejected', function () {
    [, $checklist] = makeTaskWithChecklist();
    $pic = makeUserWithRoles([Role::PIC]);
    $item = WorkItem::create([
        'task_id' => $checklist->task_id,
        'task_checklist_id' => $checklist->id,
        'responsible_department_id' => $checklist->target_department_id,
        'assignee_id' => $pic->id,
        'operational_date' => now()->toDateString(),
        'available_at' => now()->subHour(),
        'deadline_at' => now()->addHours(2),
        'failure_at' => now()->addHours(3),
        'required_evidence_count' => 2,
    ]);

    $response = $this->actingAs($pic, 'sanctum')->postJson("/api/v1/work-items/{$item->id}/submission", [
        'submit' => true,
    ]);

    $response->assertStatus(422);
    expect($item->fresh()->execution_status)->toBe(WorkItem::EXECUTION_PENDING);
});

test('a pic cannot submit before the work item is available', function () {
    [, $checklist] = makeTaskWithChecklist();
    $pic = makeUserWithRoles([Role::PIC]);
    $item = WorkItem::create([
        'task_id' => $checklist->task_id,
        'task_checklist_id' => $checklist->id,
        'responsible_department_id' => $checklist->target_department_id,
        'assignee_id' => $pic->id,
        'operational_date' => now()->addDay()->toDateString(),
        'available_at' => now()->addDay(),
        'deadline_at' => now()->addDay()->addHours(9),
        'failure_at' => now()->addDays(2)->startOfDay(),
    ]);

    $response = $this->actingAs($pic, 'sanctum')->postJson("/api/v1/work-items/{$item->id}/submission", [
        'notes' => 'Too early',
    ]);

    $response->assertStatus(422);
});

test('a locked work item cannot be edited again', function () {
    [, $checklist] = makeTaskWithChecklist();
    $pic = makeUserWithRoles([Role::PIC]);
    $item = WorkItem::create([
        'task_id' => $checklist->task_id,
        'task_checklist_id' => $checklist->id,
        'responsible_department_id' => $checklist->target_department_id,
        'assignee_id' => $pic->id,
        'operational_date' => now()->toDateString(),
        'available_at' => now()->subHour(),
        'deadline_at' => now()->addHours(2),
        'failure_at' => now()->addHours(3),
        'locked_at' => now(),
        'execution_status' => WorkItem::EXECUTION_FAILED,
        'compliance_status' => WorkItem::COMPLIANCE_FAILED,
    ]);

    $response = $this->actingAs($pic, 'sanctum')->postJson("/api/v1/work-items/{$item->id}/submission", [
        'notes' => 'Trying to edit locked history',
    ]);

    $response->assertStatus(422);
});

test('a non-assignee cannot submit for someone else\'s work item', function () {
    [, $checklist] = makeTaskWithChecklist();
    $pic = makeUserWithRoles([Role::PIC]);
    $otherPic = makeUserWithRoles([Role::PIC]);
    $item = WorkItem::create([
        'task_id' => $checklist->task_id,
        'task_checklist_id' => $checklist->id,
        'responsible_department_id' => $checklist->target_department_id,
        'assignee_id' => $pic->id,
        'operational_date' => now()->toDateString(),
        'available_at' => now()->subHour(),
        'deadline_at' => now()->addHours(2),
        'failure_at' => now()->addHours(3),
    ]);

    $this->actingAs($otherPic, 'sanctum')->postJson("/api/v1/work-items/{$item->id}/submission", [
        'notes' => 'Not mine',
    ])->assertForbidden();
});
