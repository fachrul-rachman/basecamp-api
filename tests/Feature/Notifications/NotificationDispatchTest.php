<?php

use App\Models\Department;
use App\Models\DepartmentRequest;
use App\Models\Finding;
use App\Models\Role;
use App\Models\SlaInstance;
use App\Models\SlaSetting;
use App\Models\WorkItem;
use App\Notifications\DepartmentRequestEvent;
use App\Notifications\FindingEvent;
use App\Notifications\WorkItemEvent;
use App\Services\Scheduling\WorkItemGenerator;
use Illuminate\Support\Facades\Notification;

test('creating a cross-department checklist notifies the target department managers', function () {
    Notification::fake();

    ['targetManager' => $targetManager] = createCrossDepartmentRequest();

    Notification::assertSentTo($targetManager, DepartmentRequestEvent::class);
});

test('h-1 generation notifies the assigned pic of new work', function () {
    Notification::fake();

    $department = Department::factory()->create();
    makeManager($department);
    $pic = makeUserWithRoles([Role::PIC]);
    $pic->departments()->attach($department->id);

    [$task, $checklist] = makeTaskWithChecklist(['target_department_id' => $department->id], ['department' => $department]);

    DepartmentRequest::create([
        'task_id' => $task->id,
        'task_checklist_id' => $checklist->id,
        'owner_department_id' => $department->id,
        'target_department_id' => $department->id,
        'status' => DepartmentRequest::STATUS_ASSIGNED,
        'assigned_pic_id' => $pic->id,
    ]);

    app(WorkItemGenerator::class)->generateForDate(now()->addDay());

    Notification::assertSentTo($pic, WorkItemEvent::class, fn ($n) => $n->toArray($pic)['reason'] === WorkItemEvent::ASSIGNED);
});

test('reassigning a work item notifies the new pic', function () {
    Notification::fake();
    [$item, $originalPic] = makeAvailableWorkItem();
    $department = Department::find($item->responsible_department_id);
    $manager = makeManager($department);
    $newPic = makeUserWithRoles([Role::PIC]);
    $newPic->departments()->attach($department->id);

    $this->actingAs($manager, 'sanctum')->postJson("/api/v1/work-items/{$item->id}/reassign", [
        'pic_id' => $newPic->id,
    ])->assertOk();

    Notification::assertSentTo($newPic, WorkItemEvent::class, fn ($n) => $n->toArray($newPic)['reason'] === WorkItemEvent::REASSIGNED);
});

test('a newly late work item notifies its assignee to follow up', function () {
    Notification::fake();
    [, $checklist] = makeTaskWithChecklist();
    $pic = makeUserWithRoles([Role::PIC]);

    WorkItem::create([
        'task_id' => $checklist->task_id,
        'task_checklist_id' => $checklist->id,
        'responsible_department_id' => $checklist->target_department_id,
        'assignee_id' => $pic->id,
        'operational_date' => now()->toDateString(),
        'available_at' => now()->subHours(3),
        'deadline_at' => now()->subHour(),
        'failure_at' => now()->addHour(),
    ]);

    $this->artisan('work-items:evaluate');

    Notification::assertSentTo($pic, WorkItemEvent::class, fn ($n) => $n->toArray($pic)['reason'] === WorkItemEvent::FOLLOW_UP_REQUIRED);
});

test('reopening a finding notifies the assignee', function () {
    [$finding, $manager, $pic] = makeFailedFindingSetup();
    Notification::fake();

    $this->actingAs($manager, 'sanctum')->postJson("/api/v1/findings/{$finding->id}/reopen-work", [
        'deadline_at' => now()->addHours(12)->toIso8601String(),
        'reason' => 'Another chance',
    ])->assertOk();

    Notification::assertSentTo($pic, WorkItemEvent::class, fn ($n) => $n->toArray($pic)['reason'] === WorkItemEvent::REOPENED);
});

test('an automatic finding notifies the responsible manager', function () {
    Notification::fake();
    [$finding, $manager] = makeFailedFindingSetup();

    Notification::assertSentTo(
        $manager,
        FindingEvent::class,
        fn ($n) => $n->toArray($manager)['reason'] === FindingEvent::REQUIRES_MANAGER_ACTION
            && $n->toArray($manager)['finding_id'] === $finding->id
    );
});

test('a manager explanation notifies iso', function () {
    [$finding, $manager] = makeFailedFindingSetup();
    $iso = makeUserWithRoles([Role::ISO]);
    Notification::fake();

    $this->actingAs($manager, 'sanctum')->postJson("/api/v1/findings/{$finding->id}/explanation", [
        'notes' => 'x',
    ])->assertOk();

    Notification::assertSentTo($iso, FindingEvent::class, fn ($n) => $n->toArray($iso)['reason'] === FindingEvent::EXPLANATION_AWAITING_REVIEW);
});

test('an iso review notifies the explaining manager', function () {
    [$finding, $manager] = makeFailedFindingSetup();
    $this->actingAs($manager, 'sanctum')->postJson("/api/v1/findings/{$finding->id}/explanation", ['notes' => 'x'])->assertOk();
    $iso = makeUserWithRoles([Role::ISO]);
    Notification::fake();

    $this->actingAs($iso, 'sanctum')->postJson("/api/v1/findings/{$finding->id}/iso-review", [
        'decision' => 'reject',
    ])->assertOk();

    Notification::assertSentTo($manager, FindingEvent::class, fn ($n) => $n->toArray($manager)['reason'] === FindingEvent::ISO_FEEDBACK);
});

test('manager sla escalation notifies iso', function () {
    [$finding] = makeFailedFindingSetup();
    SlaSetting::create(['scope_type' => SlaSetting::SCOPE_ISO, 'scope_id' => null, 'minutes' => 480]);
    $iso = makeUserWithRoles([Role::ISO]);
    SlaInstance::first()->update(['due_at' => now()->subMinute()]);
    Notification::fake();

    $this->artisan('findings:evaluate-sla');

    Notification::assertSentTo($iso, FindingEvent::class, fn ($n) => $n->toArray($iso)['reason'] === FindingEvent::ESCALATED_TO_ISO);
});

test('creating a manual audit finding notifies the target manager', function () {
    $department = Department::factory()->create();
    $manager = makeManager($department);
    $iso = makeUserWithRoles([Role::ISO]);
    Notification::fake();

    $this->actingAs($iso, 'sanctum')->postJson('/api/v1/audit-findings', [
        'title' => 'X',
        'target_department_id' => $department->id,
        'target_manager_id' => $manager->id,
        'status' => Finding::STATUS_OPEN,
    ])->assertCreated();

    Notification::assertSentTo($manager, FindingEvent::class, fn ($n) => $n->toArray($manager)['reason'] === FindingEvent::AUDIT_ASSIGNED);
});

test('a manager response to a manual audit finding notifies the creating iso', function () {
    [$findingId, $iso, $manager] = makeAuditFinding();
    Notification::fake();

    $this->actingAs($manager, 'sanctum')->post("/api/v1/audit-findings/{$findingId}/manager-response", [
        'notes' => 'Handled',
    ])->assertOk();

    Notification::assertSentTo($iso, FindingEvent::class, fn ($n) => $n->toArray($iso)['reason'] === FindingEvent::AUDIT_RESPONSE_AWAITING_REVIEW);
});

test('an iso review of a manual audit finding notifies the target manager', function () {
    [$findingId, $iso, $manager] = makeAuditFinding();
    Notification::fake();

    $this->actingAs($iso, 'sanctum')->postJson("/api/v1/audit-findings/{$findingId}/review", [
        'status' => Finding::STATUS_CLOSED,
    ])->assertOk();

    Notification::assertSentTo($manager, FindingEvent::class, fn ($n) => $n->toArray($manager)['reason'] === FindingEvent::ISO_FEEDBACK);
});
