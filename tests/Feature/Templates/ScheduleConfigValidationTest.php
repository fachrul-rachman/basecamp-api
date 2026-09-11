<?php

use App\Models\Role;

test('a daily checklist requires start and end time', function () {
    $admin = makeUserWithRoles([Role::ADMIN]);

    $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/templates', [
        'name' => 'Bad Daily',
        'checklists' => [
            ['title' => 'X', 'schedule_type' => 'daily', 'schedule_config' => []],
        ],
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors([
        'checklists.0.schedule_config.start_time',
        'checklists.0.schedule_config.end_time',
    ]);
});

test('a weekly checklist requires weekdays', function () {
    $admin = makeUserWithRoles([Role::ADMIN]);

    $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/templates', [
        'name' => 'Bad Weekly',
        'checklists' => [
            ['title' => 'X', 'schedule_type' => 'weekly', 'schedule_config' => ['start_time' => '08:00', 'end_time' => '09:00']],
        ],
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['checklists.0.schedule_config.weekdays']);
});

test('a weekly quota checklist requires period and target count', function () {
    $admin = makeUserWithRoles([Role::ADMIN]);

    $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/templates', [
        'name' => 'Bad Quota',
        'checklists' => [
            ['title' => 'X', 'schedule_type' => 'weekly_quota', 'schedule_config' => ['start_time' => '08:00', 'end_time' => '09:00']],
        ],
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors([
        'checklists.0.schedule_config.period',
        'checklists.0.schedule_config.target_count',
    ]);
});

test('a monthly checklist requires a valid day of month', function () {
    $admin = makeUserWithRoles([Role::ADMIN]);

    $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/templates', [
        'name' => 'Bad Monthly',
        'checklists' => [
            ['title' => 'X', 'schedule_type' => 'monthly', 'schedule_config' => [
                'start_time' => '08:00', 'end_time' => '09:00', 'day_of_month' => 40,
            ]],
        ],
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['checklists.0.schedule_config.day_of_month']);
});

test('a one-time checklist requires a date', function () {
    $admin = makeUserWithRoles([Role::ADMIN]);

    $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/templates', [
        'name' => 'Bad One Time',
        'checklists' => [
            ['title' => 'X', 'schedule_type' => 'one_time', 'schedule_config' => ['start_time' => '08:00', 'end_time' => '09:00']],
        ],
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['checklists.0.schedule_config.date']);
});

test('an event checklist only needs a response window, not start/end time', function () {
    $admin = makeUserWithRoles([Role::ADMIN]);

    $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/templates', [
        'name' => 'Good Event',
        'checklists' => [
            ['title' => 'X', 'schedule_type' => 'event', 'schedule_config' => ['response_window_hours' => 24]],
        ],
    ]);

    $response->assertCreated();
});

test('a valid weekly checklist is accepted', function () {
    $admin = makeUserWithRoles([Role::ADMIN]);

    $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/templates', [
        'name' => 'Good Weekly',
        'checklists' => [
            [
                'title' => 'X',
                'schedule_type' => 'weekly',
                'schedule_config' => ['start_time' => '08:00', 'end_time' => '09:00', 'weekdays' => [1, 3, 5]],
            ],
        ],
    ]);

    $response->assertCreated();
});
