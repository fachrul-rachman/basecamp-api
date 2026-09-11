<?php

use App\Models\Role;
use App\Models\WorkingCalendar;

test('admin can assign a working calendar to a user', function () {
    $admin = makeUserWithRoles([Role::ADMIN]);
    $target = makeUserWithRoles([Role::PIC]);
    $calendar = WorkingCalendar::factory()->withOfficeHours()->create();

    $response = $this->actingAs($admin, 'sanctum')->putJson("/api/v1/users/{$target->id}/work-calendar", [
        'working_calendar_id' => $calendar->id,
    ]);

    $response->assertOk()->assertJsonPath('data.id', $calendar->id);
    expect($target->fresh()->working_calendar_id)->toBe($calendar->id);
});

test('manager cannot assign a working calendar to a user', function () {
    $manager = makeUserWithRoles([Role::MANAGER]);
    $target = makeUserWithRoles([Role::PIC]);
    $calendar = WorkingCalendar::factory()->create();

    $response = $this->actingAs($manager, 'sanctum')->putJson("/api/v1/users/{$target->id}/work-calendar", [
        'working_calendar_id' => $calendar->id,
    ]);

    $response->assertForbidden();
});

test('get work-calendar returns null data when none assigned', function () {
    $admin = makeUserWithRoles([Role::ADMIN]);
    $target = makeUserWithRoles([Role::PIC]);

    $response = $this->actingAs($admin, 'sanctum')->getJson("/api/v1/users/{$target->id}/work-calendar");

    $response->assertOk()->assertJsonPath('data', null);
});
