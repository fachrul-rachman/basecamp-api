<?php

use App\Models\Finding;
use App\Models\Role;
use App\Models\SlaInstance;
use App\Models\SlaSetting;
use App\Models\WorkItem;

test('a work item that fails gets an automatic finding and a manager sla instance', function () {
    [, $checklist, $department] = makeTaskWithChecklist();
    $manager = makeManager($department);
    $pic = makeUserWithRoles([Role::PIC]);
    $pic->departments()->attach($department->id);

    SlaSetting::create(['scope_type' => SlaSetting::SCOPE_GLOBAL, 'scope_id' => null, 'minutes' => 240]);

    WorkItem::create([
        'task_id' => $checklist->task_id,
        'task_checklist_id' => $checklist->id,
        'responsible_department_id' => $checklist->target_department_id,
        'assignee_id' => $pic->id,
        'operational_date' => now()->subDay()->toDateString(),
        'available_at' => now()->subDay(),
        'deadline_at' => now()->subHours(5),
        'failure_at' => now()->subHour(),
    ]);

    $this->artisan('work-items:evaluate');

    $finding = Finding::first();
    expect($finding)->not->toBeNull();
    expect($finding->finding_type)->toBe(Finding::TYPE_FAILED);
    expect($finding->target_department_id)->toBe($department->id);
    expect($finding->target_user_id)->toBe($pic->id);
    expect($finding->status)->toBe(Finding::STATUS_WAITING_MANAGER_ACTION);

    $sla = SlaInstance::first();
    expect($sla->sla_type)->toBe(SlaInstance::TYPE_MANAGER);
    expect($sla->effective_minutes)->toBe(240);
    expect($sla->responsible_user_id)->toBe($manager->id);
    expect($sla->status)->toBe(SlaInstance::STATUS_RUNNING);
});

test('a late work item also gets an automatic finding', function () {
    [, $checklist] = makeTaskWithChecklist();
    SlaSetting::create(['scope_type' => SlaSetting::SCOPE_GLOBAL, 'scope_id' => null, 'minutes' => 240]);

    WorkItem::create([
        'task_id' => $checklist->task_id,
        'task_checklist_id' => $checklist->id,
        'responsible_department_id' => $checklist->target_department_id,
        'operational_date' => now()->toDateString(),
        'available_at' => now()->subHours(3),
        'deadline_at' => now()->subHour(),
        'failure_at' => now()->addHour(),
    ]);

    $this->artisan('work-items:evaluate');

    expect(Finding::first()->finding_type)->toBe(Finding::TYPE_LATE);
});

test('finding creation is idempotent across repeated evaluation runs', function () {
    [, $checklist, $department] = makeTaskWithChecklist();
    makeManager($department);
    SlaSetting::create(['scope_type' => SlaSetting::SCOPE_GLOBAL, 'scope_id' => null, 'minutes' => 240]);

    WorkItem::create([
        'task_id' => $checklist->task_id,
        'task_checklist_id' => $checklist->id,
        'responsible_department_id' => $checklist->target_department_id,
        'operational_date' => now()->subDay()->toDateString(),
        'available_at' => now()->subDay(),
        'deadline_at' => now()->subHours(5),
        'failure_at' => now()->subHour(),
    ]);

    $this->artisan('work-items:evaluate');
    $this->artisan('work-items:evaluate');

    expect(Finding::count())->toBe(1);
    expect(SlaInstance::count())->toBe(1);
});

test('manager sla precedence resolves manager override over department and global', function () {
    [, $checklist, $department] = makeTaskWithChecklist();
    $manager = makeManager($department);

    SlaSetting::create(['scope_type' => SlaSetting::SCOPE_GLOBAL, 'scope_id' => null, 'minutes' => 240]);
    SlaSetting::create(['scope_type' => SlaSetting::SCOPE_DEPARTMENT, 'scope_id' => $department->id, 'minutes' => 120]);
    SlaSetting::create(['scope_type' => SlaSetting::SCOPE_MANAGER, 'scope_id' => $manager->id, 'minutes' => 60]);

    WorkItem::create([
        'task_id' => $checklist->task_id,
        'task_checklist_id' => $checklist->id,
        'responsible_department_id' => $checklist->target_department_id,
        'operational_date' => now()->subDay()->toDateString(),
        'available_at' => now()->subDay(),
        'deadline_at' => now()->subHours(5),
        'failure_at' => now()->subHour(),
    ]);

    $this->artisan('work-items:evaluate');

    expect(SlaInstance::first()->effective_minutes)->toBe(60);
});

test('manager sla falls back to department then global when no more specific override exists', function () {
    [, $checklist, $department] = makeTaskWithChecklist();
    makeManager($department);

    SlaSetting::create(['scope_type' => SlaSetting::SCOPE_GLOBAL, 'scope_id' => null, 'minutes' => 240]);
    SlaSetting::create(['scope_type' => SlaSetting::SCOPE_DEPARTMENT, 'scope_id' => $department->id, 'minutes' => 120]);

    WorkItem::create([
        'task_id' => $checklist->task_id,
        'task_checklist_id' => $checklist->id,
        'responsible_department_id' => $checklist->target_department_id,
        'operational_date' => now()->subDay()->toDateString(),
        'available_at' => now()->subDay(),
        'deadline_at' => now()->subHours(5),
        'failure_at' => now()->subHour(),
    ]);

    $this->artisan('work-items:evaluate');

    expect(SlaInstance::first()->effective_minutes)->toBe(120);
});
