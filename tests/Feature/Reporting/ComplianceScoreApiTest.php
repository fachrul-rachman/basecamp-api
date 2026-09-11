<?php

use App\Models\Department;
use App\Models\Finding;
use App\Models\Role;
use Illuminate\Support\Carbon;

test('a manager can view their own score', function () {
    $manager = makeManager(Department::factory()->create());

    $response = $this->actingAs($manager, 'sanctum')->getJson("/api/v1/reports/managers/{$manager->id}/score");

    $response->assertOk()->assertJsonStructure(['data' => ['subject_id', 'period_month', 'score', 'deduction_count', 'locked']]);
});

test('a manager cannot view another managers score', function () {
    $manager = makeManager(Department::factory()->create());
    $otherManager = makeManager(Department::factory()->create());

    $this->actingAs($otherManager, 'sanctum')->getJson("/api/v1/reports/managers/{$manager->id}/score")->assertForbidden();
});

test('director can view any managers score', function () {
    $manager = makeManager(Department::factory()->create());
    $director = makeUserWithRoles([Role::DIRECTOR]);

    $this->actingAs($director, 'sanctum')->getJson("/api/v1/reports/managers/{$manager->id}/score")->assertOk();
});

test('a pic can view their own score', function () {
    $pic = makeUserWithRoles([Role::PIC]);

    $response = $this->actingAs($pic, 'sanctum')->getJson("/api/v1/reports/pics/{$pic->id}/score");

    $response->assertOk()->assertJsonStructure(['data' => ['subject_id', 'period_month', 'score', 'deduction_count', 'locked']]);
});

test('a manager can view a pic score within their own department', function () {
    $department = Department::factory()->create();
    $manager = makeManager($department);
    $pic = makeUserWithRoles([Role::PIC]);
    $pic->departments()->attach($department->id);
    $pic->load('departments');

    $this->actingAs($manager, 'sanctum')->getJson("/api/v1/reports/pics/{$pic->id}/score")->assertOk();
});

test('a pic cannot view another pics score', function () {
    $pic = makeUserWithRoles([Role::PIC]);
    $otherPic = makeUserWithRoles([Role::PIC]);

    $this->actingAs($otherPic, 'sanctum')->getJson("/api/v1/reports/pics/{$pic->id}/score")->assertForbidden();
});

test('the month query parameter selects the right period', function () {
    $pic = makeUserWithRoles([Role::PIC]);
    makeAutomaticFinding($pic, Finding::TYPE_LATE, null, Carbon::parse('2026-06-10'));

    $response = $this->actingAs($pic, 'sanctum')->getJson("/api/v1/reports/pics/{$pic->id}/score?month=2026-06");

    $response->assertOk()
        ->assertJsonPath('data.period_month', '2026-06-01')
        ->assertJsonPath('data.score', 95.0)
        ->assertJsonPath('data.deduction_count', 1);
});

test('the month query parameter resolves the correct month even when today is the 31st', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-31'));

    $pic = makeUserWithRoles([Role::PIC]);
    makeAutomaticFinding($pic, Finding::TYPE_LATE, null, Carbon::parse('2026-04-15'));

    $response = $this->actingAs($pic, 'sanctum')->getJson("/api/v1/reports/pics/{$pic->id}/score?month=2026-04");

    $response->assertOk()
        ->assertJsonPath('data.period_month', '2026-04-01')
        ->assertJsonPath('data.deduction_count', 1);

    Carbon::setTestNow();
});

test('an invalid month query parameter returns a validation error instead of a server error', function () {
    $pic = makeUserWithRoles([Role::PIC]);

    $this->actingAs($pic, 'sanctum')
        ->getJson("/api/v1/reports/pics/{$pic->id}/score?month=not-a-month")
        ->assertStatus(422);
});
