<?php

use App\Models\Holiday;
use App\Models\Role;
use App\Models\WorkingCalendar;
use Illuminate\Support\Carbon;

test('calendar conflicts endpoint reports an overlapping holiday', function () {
    $actor = makeUserWithRoles([Role::MANAGER]);
    $date = now()->addDays(3);
    Holiday::factory()->create(['date' => $date->toDateString(), 'name' => 'Independence Day']);

    $response = $this->actingAs($actor, 'sanctum')->getJson('/api/v1/calendar-conflicts?'.http_build_query([
        'starts_at' => now()->toIso8601String(),
        'ends_at' => now()->addDays(5)->toIso8601String(),
    ]));

    $response->assertOk();
    expect(collect($response->json('data.warnings'))->pluck('type'))->toContain('holiday');
});

test('calendar conflicts endpoint does not warn about holidays when works_on_holidays is true', function () {
    $actor = makeUserWithRoles([Role::MANAGER]);
    $date = now()->addDays(3);
    Holiday::factory()->create(['date' => $date->toDateString()]);

    $response = $this->actingAs($actor, 'sanctum')->getJson('/api/v1/calendar-conflicts?'.http_build_query([
        'starts_at' => now()->toIso8601String(),
        'ends_at' => now()->addDays(5)->toIso8601String(),
        'works_on_holidays' => true,
    ]));

    $response->assertOk();
    expect(collect($response->json('data.warnings'))->pluck('type'))->not->toContain('holiday');
});

test('calendar conflicts endpoint reports a pic non-working day', function () {
    $actor = makeUserWithRoles([Role::MANAGER]);
    $pic = makeUserWithRoles([Role::PIC]);
    $calendar = WorkingCalendar::factory()->withOfficeHours()->create();
    $pic->update(['working_calendar_id' => $calendar->id]);

    $saturday = Carbon::parse('next saturday');

    $response = $this->actingAs($actor, 'sanctum')->getJson('/api/v1/calendar-conflicts?'.http_build_query([
        'starts_at' => $saturday->toIso8601String(),
        'ends_at' => $saturday->toIso8601String(),
        'pic_id' => $pic->id,
    ]));

    $response->assertOk();
    expect(collect($response->json('data.warnings'))->pluck('type'))->toContain('assignee_non_working_day');
});
