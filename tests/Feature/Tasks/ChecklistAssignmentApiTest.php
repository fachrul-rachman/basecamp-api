<?php

use App\Models\Department;
use App\Models\Role;

test('manager sets a default assignee on their own checklist', function () {
    $department = Department::factory()->create();
    $manager = makeManager($department);
    [$task, $checklist] = makeTaskWithChecklist([], ['department' => $department]);
    $pic = makeUserWithRoles([Role::PIC]);
    $pic->departments()->attach($department->id);

    $response = $this->actingAs($manager, 'sanctum')
        ->postJson("/api/v1/tasks/{$task->id}/checklists/{$checklist->id}/assignee", ['pic_id' => $pic->id]);

    $response->assertOk()->assertJsonPath('data.default_assignee_id', $pic->id);
});

test('manager cannot assign a pic outside the checklist department', function () {
    $department = Department::factory()->create();
    $manager = makeManager($department);
    [$task, $checklist] = makeTaskWithChecklist([], ['department' => $department]);
    $outsider = makeUserWithRoles([Role::PIC]);

    $response = $this->actingAs($manager, 'sanctum')
        ->postJson("/api/v1/tasks/{$task->id}/checklists/{$checklist->id}/assignee", ['pic_id' => $outsider->id]);

    $response->assertStatus(422);
});

test('a non-owner manager cannot assign a checklist', function () {
    $department = Department::factory()->create();
    $otherDepartment = Department::factory()->create();
    $manager = makeManager($otherDepartment);
    [$task, $checklist] = makeTaskWithChecklist([], ['department' => $department]);
    $pic = makeUserWithRoles([Role::PIC]);

    $response = $this->actingAs($manager, 'sanctum')
        ->postJson("/api/v1/tasks/{$task->id}/checklists/{$checklist->id}/assignee", ['pic_id' => $pic->id]);

    $response->assertForbidden();
});

test('assigning a weekly_quota checklist via this endpoint is rejected', function () {
    $department = Department::factory()->create();
    $manager = makeManager($department);
    [$task, $checklist] = makeTaskWithChecklist(
        ['schedule_type' => 'weekly_quota', 'schedule_config' => ['period' => 'week', 'target_count' => 2]],
        ['department' => $department]
    );
    $pic = makeUserWithRoles([Role::PIC]);
    $pic->departments()->attach($department->id);

    $response = $this->actingAs($manager, 'sanctum')
        ->postJson("/api/v1/tasks/{$task->id}/checklists/{$checklist->id}/assignee", ['pic_id' => $pic->id]);

    $response->assertStatus(422);
});

test('manager creates a temporary assignee override', function () {
    $department = Department::factory()->create();
    $manager = makeManager($department);
    [$task, $checklist] = makeTaskWithChecklist([], ['department' => $department]);
    $pic = makeUserWithRoles([Role::PIC]);
    $pic->departments()->attach($department->id);

    $response = $this->actingAs($manager, 'sanctum')
        ->postJson("/api/v1/tasks/{$task->id}/checklists/{$checklist->id}/assignee-overrides", [
            'pic_id' => $pic->id,
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addDays(2)->toDateString(),
        ]);

    $response->assertCreated()->assertJsonPath('data.assignee_id', $pic->id);
});

test('the checklist resource exposes the effective assignee', function () {
    $department = Department::factory()->create();
    $manager = makeManager($department);
    [$task, $checklist] = makeTaskWithChecklist([], ['department' => $department]);
    $pic = makeUserWithRoles([Role::PIC]);
    $pic->departments()->attach($department->id);

    $this->actingAs($manager, 'sanctum')
        ->postJson("/api/v1/tasks/{$task->id}/checklists/{$checklist->id}/assignee", ['pic_id' => $pic->id]);

    $response = $this->actingAs($manager, 'sanctum')->getJson("/api/v1/tasks/{$task->id}/checklists");

    $response->assertOk()->assertJsonPath('data.0.effective_assignee_id', $pic->id);
});
