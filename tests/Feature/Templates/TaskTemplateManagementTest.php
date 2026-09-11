<?php

use App\Models\Department;
use App\Models\Role;
use App\Models\TaskTemplate;

test('admin can create a template with checklists and a department scope', function () {
    $admin = makeUserWithRoles([Role::ADMIN]);
    $department = Department::factory()->create();

    $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/templates', [
        'name' => 'Daily Cleaning',
        'description' => 'Standard daily cleaning checklist',
        'department_ids' => [$department->id],
        'checklists' => [
            [
                'title' => 'Mop the floor',
                'schedule_type' => 'daily',
                'schedule_config' => ['start_time' => '08:00', 'end_time' => '10:00'],
                'evidence_min_count' => 1,
            ],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Daily Cleaning')
        ->assertJsonPath('data.checklists.0.title', 'Mop the floor')
        ->assertJsonPath('data.departments.0.id', $department->id);
});

test('non-admin cannot create a template', function () {
    $manager = makeUserWithRoles([Role::MANAGER]);

    $response = $this->actingAs($manager, 'sanctum')->postJson('/api/v1/templates', [
        'name' => 'X',
    ]);

    $response->assertForbidden();
});

test('non-admin cannot update or delete a template', function () {
    $manager = makeUserWithRoles([Role::MANAGER]);
    $template = TaskTemplate::factory()->create();

    $this->actingAs($manager, 'sanctum')->patchJson("/api/v1/templates/{$template->id}", ['name' => 'Y'])
        ->assertForbidden();

    $this->actingAs($manager, 'sanctum')->deleteJson("/api/v1/templates/{$template->id}")
        ->assertForbidden();
});

test('deleting a template deactivates rather than removing it', function () {
    $admin = makeUserWithRoles([Role::ADMIN]);
    $template = TaskTemplate::factory()->create();

    $this->actingAs($admin, 'sanctum')->deleteJson("/api/v1/templates/{$template->id}")->assertOk();

    expect($template->fresh())->not->toBeNull();
    expect($template->fresh()->is_active)->toBeFalse();
});

test('updating checklists replaces the previous set', function () {
    $admin = makeUserWithRoles([Role::ADMIN]);
    $template = TaskTemplate::factory()->create();
    $template->checklists()->create([
        'title' => 'Old checklist',
        'schedule_type' => 'daily',
        'schedule_config' => ['start_time' => '08:00', 'end_time' => '09:00'],
    ]);

    $response = $this->actingAs($admin, 'sanctum')->patchJson("/api/v1/templates/{$template->id}", [
        'checklists' => [
            [
                'title' => 'New checklist',
                'schedule_type' => 'weekly',
                'schedule_config' => ['start_time' => '08:00', 'end_time' => '09:00', 'weekdays' => [1, 3]],
            ],
        ],
    ]);

    $response->assertOk();
    expect($template->checklists()->count())->toBe(1);
    expect($template->checklists()->first()->title)->toBe('New checklist');
});

test('a template with no department restriction is visible to every department', function () {
    $globalTemplate = TaskTemplate::factory()->create();

    $department = Department::factory()->create();
    $pic = makeUserWithRoles([Role::PIC]);
    $pic->departments()->attach($department->id);
    $pic->load('departments');

    $response = $this->actingAs($pic, 'sanctum')->getJson('/api/v1/templates');

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('id'))->toContain($globalTemplate->id);
});

test('a department-restricted template is hidden from unrelated departments', function () {
    $ownDepartment = Department::factory()->create();
    $otherDepartment = Department::factory()->create();

    $restrictedTemplate = TaskTemplate::factory()->create();
    $restrictedTemplate->departments()->attach($otherDepartment->id);

    $pic = makeUserWithRoles([Role::PIC]);
    $pic->departments()->attach($ownDepartment->id);
    $pic->load('departments');

    $response = $this->actingAs($pic, 'sanctum')->getJson('/api/v1/templates');

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('id'))->not->toContain($restrictedTemplate->id);
});

test('inactive templates are hidden from non-admin roles', function () {
    $inactive = TaskTemplate::factory()->create(['is_active' => false]);
    $pic = makeUserWithRoles([Role::PIC]);

    $response = $this->actingAs($pic, 'sanctum')->getJson('/api/v1/templates');

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('id'))->not->toContain($inactive->id);
});
