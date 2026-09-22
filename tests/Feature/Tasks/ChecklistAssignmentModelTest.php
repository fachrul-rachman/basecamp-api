<?php

use App\Models\Role;
use App\Models\WorkItem;

test('a checklist can have a default assignee and temporary overrides', function () {
    [, $checklist] = makeTaskWithChecklist();
    $pic = makeUserWithRoles([Role::PIC]);

    $checklist->update(['default_assignee_id' => $pic->id]);

    expect($checklist->fresh()->default_assignee_id)->toBe($pic->id);
    expect($checklist->fresh()->defaultAssignee->id)->toBe($pic->id);

    $override = $checklist->assigneeOverrides()->create([
        'assignee_id' => $pic->id,
        'starts_at' => '2026-09-22',
        'ends_at' => '2026-09-24',
        'created_by' => $pic->id,
    ]);

    expect($checklist->assigneeOverrides()->count())->toBe(1);
    expect($override->assignee->id)->toBe($pic->id);
    expect($override->starts_at->toDateString())->toBe('2026-09-22');
});

test('a checklist exposes the work items generated from it', function () {
    [, $checklist] = makeTaskWithChecklist();

    $item = WorkItem::create([
        'task_id' => $checklist->task_id,
        'task_checklist_id' => $checklist->id,
        'responsible_department_id' => $checklist->target_department_id,
        'operational_date' => now()->toDateString(),
        'available_at' => now(),
        'deadline_at' => now()->addHours(2),
        'failure_at' => now()->addHours(3),
    ]);

    expect($checklist->workItems()->pluck('id'))->toContain($item->id);
});
