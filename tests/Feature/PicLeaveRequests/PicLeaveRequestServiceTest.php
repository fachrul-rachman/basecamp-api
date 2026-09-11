<?php

use App\Models\AuditLog;
use App\Models\Finding;
use App\Models\PicLeaveRequest;
use App\Models\Role;
use App\Models\SlaSetting;
use App\Models\WorkItem;
use App\Notifications\PicLeaveRequestEvent;
use App\Services\PicLeaveRequestService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

test('create rejects an end date before the start date', function () {
    [$manager, $pic] = makePicInManagedDepartment();

    expect(fn () => app(PicLeaveRequestService::class)->create(
        $manager, $pic, Carbon::parse('2026-11-05'), Carbon::parse('2026-11-01'), 'Trip', null
    ))->toThrow(ValidationException::class);
});

test('create rejects an overlapping pending request for the same pic', function () {
    [$manager, $pic] = makePicInManagedDepartment();
    $service = app(PicLeaveRequestService::class);

    $service->create($manager, $pic, Carbon::parse('2026-11-01'), Carbon::parse('2026-11-05'), 'Trip', null);

    expect(fn () => $service->create(
        $manager, $pic, Carbon::parse('2026-11-03'), Carbon::parse('2026-11-07'), 'Trip 2', null
    ))->toThrow(ValidationException::class);
});

test('create notifies iso', function () {
    Notification::fake();
    [$manager, $pic] = makePicInManagedDepartment();
    $iso = makeUserWithRoles([Role::ISO]);

    app(PicLeaveRequestService::class)->create(
        $manager, $pic, Carbon::parse('2026-11-01'), Carbon::parse('2026-11-05'), 'Trip', null
    );

    Notification::assertSentTo($iso, PicLeaveRequestEvent::class, fn ($n) => (function () use ($n) {
        $prop = (new ReflectionClass($n))->getProperty('reason');
        $prop->setAccessible(true);

        return $prop->getValue($n) === PicLeaveRequestEvent::SUBMITTED;
    })());
});

test('approve notifies both the requesting manager and the pic', function () {
    Notification::fake();
    [$manager, $pic] = makePicInManagedDepartment();
    $iso = makeUserWithRoles([Role::ISO]);
    $service = app(PicLeaveRequestService::class);
    $leaveRequest = $service->create($manager, $pic, Carbon::parse('2026-11-01'), Carbon::parse('2026-11-05'), 'Trip', null);

    $service->approve($iso, $leaveRequest, null);

    Notification::assertSentTo($manager, PicLeaveRequestEvent::class);
    Notification::assertSentTo($pic, PicLeaveRequestEvent::class);
});

test('reject notifies both the requesting manager and the pic', function () {
    Notification::fake();
    [$manager, $pic] = makePicInManagedDepartment();
    $iso = makeUserWithRoles([Role::ISO]);
    $service = app(PicLeaveRequestService::class);
    $leaveRequest = $service->create($manager, $pic, Carbon::parse('2026-11-01'), Carbon::parse('2026-11-05'), 'Trip', null);

    $service->reject($iso, $leaveRequest, 'Not plausible');

    Notification::assertSentTo($manager, PicLeaveRequestEvent::class);
    Notification::assertSentTo($pic, PicLeaveRequestEvent::class);
});

test('approve excuses a not-yet-evaluated work item instead of letting it fail', function () {
    [$manager, $pic, $department] = makePicInManagedDepartment();
    [, $checklist] = makeTaskWithChecklist(
        checklistOverrides: ['target_department_id' => $department->id],
        taskOverrides: ['attributes' => ['owner_department_id' => $department->id]],
    );

    $item = WorkItem::create([
        'task_id' => $checklist->task_id,
        'task_checklist_id' => $checklist->id,
        'responsible_department_id' => $department->id,
        'assignee_id' => $pic->id,
        'operational_date' => '2026-11-02',
        'available_at' => Carbon::parse('2026-11-02 08:00'),
        'deadline_at' => Carbon::parse('2026-11-02 17:00'),
        'failure_at' => Carbon::parse('2026-11-03 00:00'),
    ]);

    $iso = makeUserWithRoles([Role::ISO]);
    $service = app(PicLeaveRequestService::class);
    $leaveRequest = $service->create($manager, $pic, Carbon::parse('2026-11-01'), Carbon::parse('2026-11-05'), 'Trip', null);

    $service->approve($iso, $leaveRequest, 'Approved, enjoy');

    $this->artisan('work-items:evaluate');

    expect($item->fresh())
        ->execution_status->toBe(WorkItem::EXECUTION_CANCELLED)
        ->compliance_status->toBe(WorkItem::COMPLIANCE_NOT_APPLICABLE);
    expect(Finding::where('work_item_id', $item->id)->exists())->toBeFalse();
});

test('approve retroactively resolves a finding that was already created before review', function () {
    [$manager, $pic, $department] = makePicInManagedDepartment();
    [, $checklist] = makeTaskWithChecklist(
        checklistOverrides: ['target_department_id' => $department->id],
        taskOverrides: ['attributes' => ['owner_department_id' => $department->id]],
    );

    SlaSetting::query()->firstOrCreate(
        ['scope_type' => SlaSetting::SCOPE_GLOBAL, 'scope_id' => null],
        ['minutes' => 240]
    );

    $item = WorkItem::create([
        'task_id' => $checklist->task_id,
        'task_checklist_id' => $checklist->id,
        'responsible_department_id' => $department->id,
        'assignee_id' => $pic->id,
        'operational_date' => now()->subDay()->toDateString(),
        'available_at' => now()->subDay(),
        'deadline_at' => now()->subHours(5),
        'failure_at' => now()->subHour(),
    ]);

    $this->artisan('work-items:evaluate');
    $finding = Finding::where('work_item_id', $item->id)->firstOrFail();
    expect($finding->status)->toBe(Finding::STATUS_WAITING_MANAGER_ACTION);

    $iso = makeUserWithRoles([Role::ISO]);
    $service = app(PicLeaveRequestService::class);
    $leaveRequest = $service->create(
        $manager, $pic, now()->subDay(), now()->subDay(), 'Sick, submitted late', null
    );

    $service->approve($iso, $leaveRequest, null);

    expect($item->fresh())->execution_status->toBe(WorkItem::EXECUTION_CANCELLED);
    expect($finding->fresh())
        ->status->toBe(Finding::STATUS_RESOLVED)
        ->resolution_type->toBe('leave_approved');
});

test('approve records the excused work item and finding ids on the audit log', function () {
    [$manager, $pic, $department] = makePicInManagedDepartment();
    [, $checklist] = makeTaskWithChecklist(
        checklistOverrides: ['target_department_id' => $department->id],
        taskOverrides: ['attributes' => ['owner_department_id' => $department->id]],
    );

    SlaSetting::query()->firstOrCreate(
        ['scope_type' => SlaSetting::SCOPE_GLOBAL, 'scope_id' => null],
        ['minutes' => 240]
    );

    $item = WorkItem::create([
        'task_id' => $checklist->task_id,
        'task_checklist_id' => $checklist->id,
        'responsible_department_id' => $department->id,
        'assignee_id' => $pic->id,
        'operational_date' => now()->subDay()->toDateString(),
        'available_at' => now()->subDay(),
        'deadline_at' => now()->subHours(5),
        'failure_at' => now()->subHour(),
    ]);

    $this->artisan('work-items:evaluate');
    $finding = Finding::where('work_item_id', $item->id)->firstOrFail();

    $iso = makeUserWithRoles([Role::ISO]);
    $service = app(PicLeaveRequestService::class);
    $leaveRequest = $service->create(
        $manager, $pic, now()->subDay(), now()->subDay(), 'Sick, submitted late', null
    );

    $service->approve($iso, $leaveRequest, null);

    $log = AuditLog::where('action', 'pic_leave_request.approved')
        ->where('entity_id', $leaveRequest->id)
        ->firstOrFail();

    expect($log->metadata['work_item_ids'])->toBe([$item->id]);
    expect($log->metadata['finding_ids'])->toBe([$finding->id]);
});

test('approve does not touch a work item that was reassigned away from the pic', function () {
    [$manager, $pic, $department] = makePicInManagedDepartment();
    $otherPic = makeUserWithRoles([Role::PIC]);
    [, $checklist] = makeTaskWithChecklist(
        checklistOverrides: ['target_department_id' => $department->id],
        taskOverrides: ['attributes' => ['owner_department_id' => $department->id]],
    );

    $item = WorkItem::create([
        'task_id' => $checklist->task_id,
        'task_checklist_id' => $checklist->id,
        'responsible_department_id' => $department->id,
        'assignee_id' => $otherPic->id, // already reassigned before approval
        'operational_date' => '2026-11-02',
        'available_at' => Carbon::parse('2026-11-02 08:00'),
        'deadline_at' => Carbon::parse('2026-11-02 17:00'),
        'failure_at' => Carbon::parse('2026-11-03 00:00'),
    ]);

    $iso = makeUserWithRoles([Role::ISO]);
    $service = app(PicLeaveRequestService::class);
    $leaveRequest = $service->create($manager, $pic, Carbon::parse('2026-11-01'), Carbon::parse('2026-11-05'), 'Trip', null);

    $service->approve($iso, $leaveRequest, null);

    expect($item->fresh()->execution_status)->toBe(WorkItem::EXECUTION_PENDING);
});

test('approve leaves an already-completed work item untouched', function () {
    [$manager, $pic, $department] = makePicInManagedDepartment();
    [, $checklist] = makeTaskWithChecklist(
        checklistOverrides: ['target_department_id' => $department->id],
        taskOverrides: ['attributes' => ['owner_department_id' => $department->id]],
    );

    $item = WorkItem::create([
        'task_id' => $checklist->task_id,
        'task_checklist_id' => $checklist->id,
        'responsible_department_id' => $department->id,
        'assignee_id' => $pic->id,
        'operational_date' => '2026-11-02',
        'available_at' => Carbon::parse('2026-11-02 08:00'),
        'deadline_at' => Carbon::parse('2026-11-02 17:00'),
        'failure_at' => Carbon::parse('2026-11-03 00:00'),
        'execution_status' => WorkItem::EXECUTION_COMPLETED,
        'compliance_status' => WorkItem::COMPLIANCE_ON_TIME,
    ]);

    $iso = makeUserWithRoles([Role::ISO]);
    $service = app(PicLeaveRequestService::class);
    $leaveRequest = $service->create($manager, $pic, Carbon::parse('2026-11-01'), Carbon::parse('2026-11-05'), 'Trip', null);

    $service->approve($iso, $leaveRequest, null);

    expect($item->fresh())
        ->execution_status->toBe(WorkItem::EXECUTION_COMPLETED)
        ->compliance_status->toBe(WorkItem::COMPLIANCE_ON_TIME);
});

test('approve only excuses a weekly_quota work item when the whole period is inside the leave range', function () {
    [$manager, $pic, $department] = makePicInManagedDepartment();
    [, $checklist] = makeTaskWithChecklist(
        checklistOverrides: [
            'target_department_id' => $department->id,
            'schedule_type' => 'weekly_quota',
            'schedule_config' => ['period' => 'week', 'target_count' => 1],
        ],
        taskOverrides: ['attributes' => ['owner_department_id' => $department->id]],
    );

    // Period fully inside the leave range -> excused.
    $fullyInside = WorkItem::create([
        'task_id' => $checklist->task_id,
        'task_checklist_id' => $checklist->id,
        'responsible_department_id' => $department->id,
        'assignee_id' => $pic->id,
        'period_start' => Carbon::parse('2026-11-02 00:00'),
        'period_end' => Carbon::parse('2026-11-08 23:59:59'),
        'available_at' => Carbon::parse('2026-11-02 00:00'),
        'deadline_at' => Carbon::parse('2026-11-08 23:59:59'),
        'failure_at' => Carbon::parse('2026-11-08 23:59:59'),
    ]);

    // Period only partially overlapping the leave range -> left untouched.
    $partiallyInside = WorkItem::create([
        'task_id' => $checklist->task_id,
        'task_checklist_id' => $checklist->id,
        'responsible_department_id' => $department->id,
        'assignee_id' => $pic->id,
        'period_start' => Carbon::parse('2026-11-09 00:00'),
        'period_end' => Carbon::parse('2026-11-15 23:59:59'),
        'available_at' => Carbon::parse('2026-11-09 00:00'),
        'deadline_at' => Carbon::parse('2026-11-15 23:59:59'),
        'failure_at' => Carbon::parse('2026-11-15 23:59:59'),
    ]);

    $iso = makeUserWithRoles([Role::ISO]);
    $service = app(PicLeaveRequestService::class);
    $leaveRequest = $service->create($manager, $pic, Carbon::parse('2026-11-02'), Carbon::parse('2026-11-10'), 'Trip', null);

    $service->approve($iso, $leaveRequest, null);

    expect($fullyInside->fresh()->execution_status)->toBe(WorkItem::EXECUTION_CANCELLED);
    expect($partiallyInside->fresh()->execution_status)->toBe(WorkItem::EXECUTION_PENDING);
});

test('reject makes no changes to any work item', function () {
    [$manager, $pic, $department] = makePicInManagedDepartment();
    [, $checklist] = makeTaskWithChecklist(
        checklistOverrides: ['target_department_id' => $department->id],
        taskOverrides: ['attributes' => ['owner_department_id' => $department->id]],
    );

    $item = WorkItem::create([
        'task_id' => $checklist->task_id,
        'task_checklist_id' => $checklist->id,
        'responsible_department_id' => $department->id,
        'assignee_id' => $pic->id,
        'operational_date' => '2026-11-02',
        'available_at' => Carbon::parse('2026-11-02 08:00'),
        'deadline_at' => Carbon::parse('2026-11-02 17:00'),
        'failure_at' => Carbon::parse('2026-11-03 00:00'),
    ]);

    $iso = makeUserWithRoles([Role::ISO]);
    $service = app(PicLeaveRequestService::class);
    $leaveRequest = $service->create($manager, $pic, Carbon::parse('2026-11-01'), Carbon::parse('2026-11-05'), 'Trip', null);

    $service->reject($iso, $leaveRequest, 'Not plausible');

    expect($leaveRequest->fresh()->status)->toBe(PicLeaveRequest::STATUS_REJECTED);
    expect($item->fresh()->execution_status)->toBe(WorkItem::EXECUTION_PENDING);
});

test('approving or rejecting an already-reviewed request throws', function () {
    [$manager, $pic] = makePicInManagedDepartment();
    $iso = makeUserWithRoles([Role::ISO]);
    $service = app(PicLeaveRequestService::class);
    $leaveRequest = $service->create($manager, $pic, Carbon::parse('2026-11-01'), Carbon::parse('2026-11-05'), 'Trip', null);

    $service->approve($iso, $leaveRequest, null);

    expect(fn () => $service->approve($iso, $leaveRequest->fresh(), null))->toThrow(ValidationException::class);
});
