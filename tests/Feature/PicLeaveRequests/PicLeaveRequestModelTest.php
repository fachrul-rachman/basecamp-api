<?php

use App\Models\Department;
use App\Models\PicLeaveRequest;
use App\Models\Role;

test('a pic leave request can be created with the expected relations and defaults', function () {
    $manager = makeManager(Department::factory()->create());
    $pic = makeUserWithRoles([Role::PIC]);

    $leaveRequest = PicLeaveRequest::create([
        'pic_id' => $pic->id,
        'requested_by' => $manager->id,
        'date_from' => '2026-10-01',
        'date_to' => '2026-10-03',
        'reason' => 'Family event',
        'status' => PicLeaveRequest::STATUS_PENDING,
    ]);

    expect($leaveRequest->fresh())
        ->status->toBe(PicLeaveRequest::STATUS_PENDING)
        ->date_from->toDateString()->toBe('2026-10-01')
        ->date_to->toDateString()->toBe('2026-10-03');

    expect($leaveRequest->pic->id)->toBe($pic->id);
    expect($leaveRequest->requestedBy->id)->toBe($manager->id);
});
