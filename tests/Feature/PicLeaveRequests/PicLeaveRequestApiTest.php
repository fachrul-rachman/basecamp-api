<?php

use App\Models\Department;
use App\Models\PicLeaveRequest;
use App\Models\Role;
use App\Services\PicLeaveRequestService;
use Illuminate\Support\Carbon;

test('a manager can submit a leave request for a pic in their department', function () {
    $department = Department::factory()->create();
    $manager = makeManager($department);
    $pic = makeUserWithRoles([Role::PIC]);
    $pic->departments()->attach($department->id);

    $response = $this->actingAs($manager, 'sanctum')->postJson('/api/v1/pic-leave-requests', [
        'pic_id' => $pic->id,
        'date_from' => '2026-11-01',
        'date_to' => '2026-11-03',
        'reason' => 'Family event',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', PicLeaveRequest::STATUS_PENDING)
        ->assertJsonPath('data.pic_id', $pic->id);
});

test('a manager cannot submit a leave request for a colleague who does not hold the pic role', function () {
    $department = Department::factory()->create();
    $manager = makeManager($department);
    $otherManager = makeManager($department);

    $response = $this->actingAs($manager, 'sanctum')->postJson('/api/v1/pic-leave-requests', [
        'pic_id' => $otherManager->id,
        'date_from' => '2026-11-01',
        'date_to' => '2026-11-03',
        'reason' => 'Family event',
    ]);

    $response->assertUnprocessable();
});

test('a manager who also holds the pic role can submit a leave request for themselves', function () {
    $department = Department::factory()->create();
    $manager = makeManager($department);
    $manager->roles()->attach(Role::firstOrCreate(['code' => Role::PIC], ['name' => 'Pic'])->id);
    $manager = $manager->fresh(['roles', 'departments']);

    $response = $this->actingAs($manager, 'sanctum')->postJson('/api/v1/pic-leave-requests', [
        'pic_id' => $manager->id,
        'date_from' => '2026-11-01',
        'date_to' => '2026-11-03',
        'reason' => 'Family event',
    ]);

    $response->assertCreated()->assertJsonPath('data.pic_id', $manager->id);
});

test('a manager cannot submit a leave request for a pic outside their department', function () {
    $manager = makeManager(Department::factory()->create());
    $pic = makeUserWithRoles([Role::PIC]);
    $pic->departments()->attach(Department::factory()->create()->id);

    $response = $this->actingAs($manager, 'sanctum')->postJson('/api/v1/pic-leave-requests', [
        'pic_id' => $pic->id,
        'date_from' => '2026-11-01',
        'date_to' => '2026-11-03',
        'reason' => 'Family event',
    ]);

    $response->assertUnprocessable();
});

test('a pic cannot submit a leave request', function () {
    $pic = makeUserWithRoles([Role::PIC]);

    $this->actingAs($pic, 'sanctum')->postJson('/api/v1/pic-leave-requests', [
        'pic_id' => $pic->id,
        'date_from' => '2026-11-01',
        'date_to' => '2026-11-03',
        'reason' => 'Family event',
    ])->assertForbidden();
});

test('iso can approve a pending leave request', function () {
    $department = Department::factory()->create();
    $manager = makeManager($department);
    $pic = makeUserWithRoles([Role::PIC]);
    $pic->departments()->attach($department->id);
    $iso = makeUserWithRoles([Role::ISO]);

    $leaveRequest = app(PicLeaveRequestService::class)->create(
        $manager, $pic, Carbon::parse('2026-11-01'), Carbon::parse('2026-11-03'), 'Trip', null
    );

    $this->actingAs($iso, 'sanctum')
        ->postJson("/api/v1/pic-leave-requests/{$leaveRequest->id}/approve", ['notes' => 'ok'])
        ->assertOk()
        ->assertJsonPath('data.status', PicLeaveRequest::STATUS_APPROVED);
});

test('a manager cannot approve a leave request', function () {
    $department = Department::factory()->create();
    $manager = makeManager($department);
    $pic = makeUserWithRoles([Role::PIC]);
    $pic->departments()->attach($department->id);

    $leaveRequest = app(PicLeaveRequestService::class)->create(
        $manager, $pic, Carbon::parse('2026-11-01'), Carbon::parse('2026-11-03'), 'Trip', null
    );

    $this->actingAs($manager, 'sanctum')
        ->postJson("/api/v1/pic-leave-requests/{$leaveRequest->id}/approve", [])
        ->assertForbidden();
});

test('a manager can only list leave requests for pics in their own department', function () {
    $department = Department::factory()->create();
    $manager = makeManager($department);
    $pic = makeUserWithRoles([Role::PIC]);
    $pic->departments()->attach($department->id);

    $otherDepartment = Department::factory()->create();
    $otherPic = makeUserWithRoles([Role::PIC]);
    $otherPic->departments()->attach($otherDepartment->id);
    $otherManager = makeManager($otherDepartment);

    $service = app(PicLeaveRequestService::class);
    $service->create($manager, $pic, Carbon::parse('2026-11-01'), Carbon::parse('2026-11-03'), 'Trip', null);
    $service->create($otherManager, $otherPic, Carbon::parse('2026-11-01'), Carbon::parse('2026-11-03'), 'Trip', null);

    $response = $this->actingAs($manager, 'sanctum')->getJson('/api/v1/pic-leave-requests');

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('pic_id')->all())->toBe([$pic->id]);
});

test('a pic can view only their own leave request', function () {
    $department = Department::factory()->create();
    $manager = makeManager($department);
    $pic = makeUserWithRoles([Role::PIC]);
    $pic->departments()->attach($department->id);
    $otherPic = makeUserWithRoles([Role::PIC]);

    $leaveRequest = app(PicLeaveRequestService::class)->create(
        $manager, $pic, Carbon::parse('2026-11-01'), Carbon::parse('2026-11-03'), 'Trip', null
    );

    $this->actingAs($pic, 'sanctum')->getJson("/api/v1/pic-leave-requests/{$leaveRequest->id}")->assertOk();
    $this->actingAs($otherPic, 'sanctum')->getJson("/api/v1/pic-leave-requests/{$leaveRequest->id}")->assertForbidden();
});
