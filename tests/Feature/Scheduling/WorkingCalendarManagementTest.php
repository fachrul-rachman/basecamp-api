<?php

use App\Models\Role;
use App\Models\WorkingCalendar;

test('admin can create a working calendar with weekly hours', function () {
    $admin = makeUserWithRoles([Role::ADMIN]);

    $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/working-calendars', [
        'name' => 'Night Shift',
        'timezone' => 'Asia/Jakarta',
        'hours' => [
            ['weekday' => 1, 'is_working_day' => true, 'start_time' => '22:00', 'end_time' => '23:59'],
        ],
    ]);

    $response->assertCreated()->assertJsonPath('data.name', 'Night Shift');
    expect($response->json('data.hours'))->toHaveCount(1);
});

test('non-admin cannot create a working calendar', function () {
    $manager = makeUserWithRoles([Role::MANAGER]);

    $response = $this->actingAs($manager, 'sanctum')->postJson('/api/v1/working-calendars', [
        'name' => 'X',
    ]);

    $response->assertForbidden();
});

test('admin can add and remove a calendar exception', function () {
    $admin = makeUserWithRoles([Role::ADMIN]);
    $calendar = WorkingCalendar::factory()->create();

    $add = $this->actingAs($admin, 'sanctum')->postJson("/api/v1/working-calendars/{$calendar->id}/exceptions", [
        'date' => '2026-12-25',
        'is_working' => false,
        'reason' => 'Company retreat',
    ]);
    $add->assertCreated();
    $exceptionId = $add->json('data.exceptions.0.id');

    $this->actingAs($admin, 'sanctum')
        ->deleteJson("/api/v1/working-calendars/{$calendar->id}/exceptions/{$exceptionId}")
        ->assertOk();

    expect($calendar->exceptions()->count())->toBe(0);
});

test('admin can deactivate a working calendar', function () {
    $admin = makeUserWithRoles([Role::ADMIN]);
    $calendar = WorkingCalendar::factory()->create();

    $this->actingAs($admin, 'sanctum')->deleteJson("/api/v1/working-calendars/{$calendar->id}")->assertOk();

    expect($calendar->fresh()->is_active)->toBeFalse();
});
