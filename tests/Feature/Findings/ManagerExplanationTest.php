<?php

use App\Models\Department;
use App\Models\Finding;
use App\Models\Role;
use App\Models\SlaInstance;
use App\Models\SlaSetting;
use App\Models\WorkItem;

test('the responsible manager can submit an explanation', function () {
    [$finding, $manager] = makeFailedFindingSetup();

    $response = $this->actingAs($manager, 'sanctum')->postJson("/api/v1/findings/{$finding->id}/explanation", [
        'notes' => 'PIC was on emergency leave',
    ]);

    $response->assertOk()->assertJsonPath('data.status', Finding::STATUS_WAITING_ISO_REVIEW);
    expect(SlaInstance::first()->status)->toBe(SlaInstance::STATUS_COMPLETED);
});

test('a manager explaining their own finding is flagged self-handled', function () {
    [, $checklist, $department] = makeTaskWithChecklist();
    $managerPic = makeUserWithRoles([Role::MANAGER, Role::PIC]);
    $managerPic->departments()->attach($department->id);
    $managerPic->load('departments');
    SlaSetting::create(['scope_type' => SlaSetting::SCOPE_GLOBAL, 'scope_id' => null, 'minutes' => 240]);

    WorkItem::create([
        'task_id' => $checklist->task_id,
        'task_checklist_id' => $checklist->id,
        'responsible_department_id' => $checklist->target_department_id,
        'assignee_id' => $managerPic->id,
        'operational_date' => now()->subDay()->toDateString(),
        'available_at' => now()->subDay(),
        'deadline_at' => now()->subHours(5),
        'failure_at' => now()->subHour(),
    ]);

    $this->artisan('work-items:evaluate');
    $finding = Finding::first();

    $response = $this->actingAs($managerPic, 'sanctum')->postJson("/api/v1/findings/{$finding->id}/explanation", [
        'notes' => 'I was overwhelmed and handled it myself',
    ]);

    $response->assertOk()->assertJsonPath('data.is_self_handled', true);
});

test('an unrelated manager cannot respond to the finding', function () {
    [$finding] = makeFailedFindingSetup();
    $unrelatedManager = makeManager(Department::factory()->create());

    $this->actingAs($unrelatedManager, 'sanctum')->postJson("/api/v1/findings/{$finding->id}/explanation", [
        'notes' => 'Not my department',
    ])->assertForbidden();
});

test('a pic without manager role cannot respond to a finding', function () {
    [$finding, , $pic] = makeFailedFindingSetup();

    $this->actingAs($pic, 'sanctum')->postJson("/api/v1/findings/{$finding->id}/explanation", [
        'notes' => 'Trying without manager role',
    ])->assertForbidden();
});

test('cannot explain a finding that already has an explanation awaiting iso review', function () {
    [$finding, $manager] = makeFailedFindingSetup();
    $this->actingAs($manager, 'sanctum')->postJson("/api/v1/findings/{$finding->id}/explanation", ['notes' => 'first'])
        ->assertOk();

    $this->actingAs($manager, 'sanctum')->postJson("/api/v1/findings/{$finding->id}/explanation", ['notes' => 'second'])
        ->assertStatus(422);
});
