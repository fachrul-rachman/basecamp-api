<?php

use App\Models\WorkItem;
use App\Services\FindingService;

test('a work item past failure_at with no submission is marked failed and locked', function () {
    [, $checklist] = makeTaskWithChecklist();
    $item = WorkItem::create([
        'task_id' => $checklist->task_id,
        'task_checklist_id' => $checklist->id,
        'responsible_department_id' => $checklist->target_department_id,
        'operational_date' => now()->subDay()->toDateString(),
        'available_at' => now()->subDay(),
        'deadline_at' => now()->subHours(5),
        'failure_at' => now()->subHour(),
    ]);

    $this->artisan('work-items:evaluate')->assertExitCode(0);

    $item->refresh();
    expect($item->execution_status)->toBe(WorkItem::EXECUTION_FAILED);
    expect($item->compliance_status)->toBe(WorkItem::COMPLIANCE_FAILED);
    expect($item->locked_at)->not->toBeNull();
});

test('a work item past deadline_at but before failure_at is marked late without locking', function () {
    [, $checklist] = makeTaskWithChecklist();
    $item = WorkItem::create([
        'task_id' => $checklist->task_id,
        'task_checklist_id' => $checklist->id,
        'responsible_department_id' => $checklist->target_department_id,
        'operational_date' => now()->toDateString(),
        'available_at' => now()->subHours(3),
        'deadline_at' => now()->subHour(),
        'failure_at' => now()->addHour(),
    ]);

    $this->artisan('work-items:evaluate');

    $item->refresh();
    expect($item->compliance_status)->toBe(WorkItem::COMPLIANCE_LATE);
    expect($item->execution_status)->toBe(WorkItem::EXECUTION_PENDING);
    expect($item->locked_at)->toBeNull();
});

test('a completed work item is left untouched by the evaluation job', function () {
    [, $checklist] = makeTaskWithChecklist();
    $item = WorkItem::create([
        'task_id' => $checklist->task_id,
        'task_checklist_id' => $checklist->id,
        'responsible_department_id' => $checklist->target_department_id,
        'operational_date' => now()->subDay()->toDateString(),
        'available_at' => now()->subDay(),
        'deadline_at' => now()->subHours(5),
        'failure_at' => now()->subHour(),
        'execution_status' => WorkItem::EXECUTION_COMPLETED,
        'compliance_status' => WorkItem::COMPLIANCE_ON_TIME,
        'locked_at' => now()->subHours(4),
    ]);

    $this->artisan('work-items:evaluate');

    $item->refresh();
    expect($item->execution_status)->toBe(WorkItem::EXECUTION_COMPLETED);
    expect($item->compliance_status)->toBe(WorkItem::COMPLIANCE_ON_TIME);
});

test('a failure while creating the finding rolls back the work item status change', function () {
    [, $checklist] = makeTaskWithChecklist();
    $item = WorkItem::create([
        'task_id' => $checklist->task_id,
        'task_checklist_id' => $checklist->id,
        'responsible_department_id' => $checklist->target_department_id,
        'operational_date' => now()->subDay()->toDateString(),
        'available_at' => now()->subDay(),
        'deadline_at' => now()->subHours(5),
        'failure_at' => now()->subHour(),
    ]);

    $this->partialMock(FindingService::class, function ($mock) {
        $mock->shouldReceive('createAutomaticFinding')->andThrow(new RuntimeException('boom'));
    });

    expect(fn () => $this->artisan('work-items:evaluate'))->toThrow(RuntimeException::class);

    $item->refresh();
    expect($item->execution_status)->not->toBe(WorkItem::EXECUTION_FAILED);
    expect($item->compliance_status)->not->toBe(WorkItem::COMPLIANCE_FAILED);
    expect($item->locked_at)->toBeNull();
});
