<?php

use App\Models\Department;
use App\Models\DepartmentRequest;
use App\Models\Role;
use App\Models\WorkItem;
use App\Services\Scheduling\WorkItemGenerator;
use Illuminate\Support\Carbon;

test('generateForDate assigns the checklist default assignee to a new daily work item', function () {
    [, $checklist] = makeTaskWithChecklist(['schedule_type' => 'daily']);
    $pic = makeUserWithRoles([Role::PIC]);
    $checklist->update(['default_assignee_id' => $pic->id]);

    app(WorkItemGenerator::class)->generateForDate(now()->addDay());

    $item = WorkItem::where('task_checklist_id', $checklist->id)->first();
    expect($item)->not->toBeNull();
    expect($item->assignee_id)->toBe($pic->id);
});

test('generateForDate leaves weekly_quota work items unassigned regardless of default_assignee_id', function () {
    // weekly_quota generates the whole period's slots the day before the
    // period starts (see WorkItemGenerator::generateQuotaPeriod), so the
    // candidate date must land on the Sunday before the target Monday —
    // not an arbitrary "tomorrow" — for any slots to be created at all.
    $monday = Carbon::parse('next monday');
    [, $checklist] = makeTaskWithChecklist([
        'schedule_type' => 'weekly_quota',
        'schedule_config' => ['period' => 'week', 'target_count' => 2],
    ]);
    // default_assignee_id is never set for weekly_quota via the service
    // (rejected), but confirm the generator ignores the raw column too
    // even if it were ever set directly.
    $pic = makeUserWithRoles([Role::PIC]);
    $checklist->forceFill(['default_assignee_id' => $pic->id])->save();

    app(WorkItemGenerator::class)->generateForDate($monday->copy()->subDay());

    $items = WorkItem::where('task_checklist_id', $checklist->id)->get();
    expect($items)->not->toBeEmpty();
    expect($items->pluck('assignee_id')->filter()->isEmpty())->toBeTrue();
});

test('generateForDate still resolves cross-department assignment via the department request, unaffected by checklist default_assignee_id', function () {
    [$task, $checklist, $department] = makeTaskWithChecklist();
    $otherDepartment = Department::factory()->create();
    $checklist->update(['target_department_id' => $otherDepartment->id]);
    $decoyDefault = makeUserWithRoles([Role::PIC]);
    $checklist->forceFill(['default_assignee_id' => $decoyDefault->id])->save();
    $targetPic = makeUserWithRoles([Role::PIC]);
    DepartmentRequest::create([
        'task_id' => $task->id,
        'task_checklist_id' => $checklist->id,
        'owner_department_id' => $department->id,
        'target_department_id' => $otherDepartment->id,
        'status' => DepartmentRequest::STATUS_ASSIGNED,
        'assigned_pic_id' => $targetPic->id,
    ]);

    app(WorkItemGenerator::class)->generateForDate(now()->addDay());

    $item = WorkItem::where('task_checklist_id', $checklist->id)->first();
    expect($item->assignee_id)->toBe($targetPic->id);
});
