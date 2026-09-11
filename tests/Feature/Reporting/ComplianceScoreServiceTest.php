<?php

use App\Models\Department;
use App\Models\Finding;
use App\Models\MonthlyScore;
use App\Models\Role;
use App\Models\SlaInstance;
use App\Services\ComplianceScoreService;
use Illuminate\Support\Carbon;

test('a late finding with no resolution counts toward the pic deduction', function () {
    $pic = makeUserWithRoles([Role::PIC]);
    $month = Carbon::parse('2026-09-15');
    makeAutomaticFinding($pic, Finding::TYPE_LATE, null, Carbon::parse('2026-09-10'));

    $result = app(ComplianceScoreService::class)->picScore($pic, $month);

    expect($result)->toBe(['score' => 95.0, 'deduction_count' => 1, 'locked' => false]);
});

test('a finding resolved as explanation_accepted does not count toward the pic deduction', function () {
    $pic = makeUserWithRoles([Role::PIC]);
    $month = Carbon::parse('2026-09-15');
    makeAutomaticFinding($pic, Finding::TYPE_FAILED, 'explanation_accepted', Carbon::parse('2026-09-10'));

    $result = app(ComplianceScoreService::class)->picScore($pic, $month);

    expect($result)->toBe(['score' => 100.0, 'deduction_count' => 0, 'locked' => false]);
});

test('a finding resolved as leave_approved does not count toward the pic deduction', function () {
    $pic = makeUserWithRoles([Role::PIC]);
    $month = Carbon::parse('2026-09-15');
    makeAutomaticFinding($pic, Finding::TYPE_LATE, 'leave_approved', Carbon::parse('2026-09-10'));

    $result = app(ComplianceScoreService::class)->picScore($pic, $month);

    expect($result)->toBe(['score' => 100.0, 'deduction_count' => 0, 'locked' => false]);
});

test('a finding resolved as reopen_completed still counts toward the pic deduction', function () {
    $pic = makeUserWithRoles([Role::PIC]);
    $month = Carbon::parse('2026-09-15');
    makeAutomaticFinding($pic, Finding::TYPE_FAILED, 'reopen_completed', Carbon::parse('2026-09-10'));

    $result = app(ComplianceScoreService::class)->picScore($pic, $month);

    expect($result)->toBe(['score' => 95.0, 'deduction_count' => 1, 'locked' => false]);
});

test('the pic score floors at zero after more than 20 qualifying findings', function () {
    $pic = makeUserWithRoles([Role::PIC]);
    $month = Carbon::parse('2026-09-15');

    for ($i = 0; $i < 25; $i++) {
        makeAutomaticFinding($pic, Finding::TYPE_LATE, null, Carbon::parse('2026-09-10'));
    }

    $result = app(ComplianceScoreService::class)->picScore($pic, $month);

    expect($result)->toBe(['score' => 0.0, 'deduction_count' => 25, 'locked' => false]);
});

test('a finding outside the requested month does not count', function () {
    $pic = makeUserWithRoles([Role::PIC]);
    makeAutomaticFinding($pic, Finding::TYPE_LATE, null, Carbon::parse('2026-08-31'));

    $result = app(ComplianceScoreService::class)->picScore($pic, Carbon::parse('2026-09-15'));

    expect($result)->toBe(['score' => 100.0, 'deduction_count' => 0, 'locked' => false]);
});

test('a breached manager sla instance counts toward the manager deduction', function () {
    $manager = makeManager(Department::factory()->create());
    $pic = makeUserWithRoles([Role::PIC]);
    $finding = makeAutomaticFinding($pic, Finding::TYPE_LATE, null, Carbon::parse('2026-09-10'));
    makeManagerSlaInstance($manager, $finding, SlaInstance::STATUS_BREACHED, Carbon::parse('2026-09-11'));

    $result = app(ComplianceScoreService::class)->managerScore($manager, Carbon::parse('2026-09-15'));

    expect($result)->toBe(['score' => 95.0, 'deduction_count' => 1, 'locked' => false]);
});

test('a running or completed manager sla instance does not count', function () {
    $manager = makeManager(Department::factory()->create());
    $pic = makeUserWithRoles([Role::PIC]);
    $runningFinding = makeAutomaticFinding($pic, Finding::TYPE_LATE, null, Carbon::parse('2026-09-10'));
    $completedFinding = makeAutomaticFinding($pic, Finding::TYPE_LATE, null, Carbon::parse('2026-09-10'));
    makeManagerSlaInstance($manager, $runningFinding, SlaInstance::STATUS_RUNNING);
    makeManagerSlaInstance($manager, $completedFinding, SlaInstance::STATUS_COMPLETED);

    $result = app(ComplianceScoreService::class)->managerScore($manager, Carbon::parse('2026-09-15'));

    expect($result)->toBe(['score' => 100.0, 'deduction_count' => 0, 'locked' => false]);
});

test('a breached iso sla instance does not count toward the manager deduction', function () {
    $manager = makeManager(Department::factory()->create());
    $pic = makeUserWithRoles([Role::PIC]);
    $finding = makeAutomaticFinding($pic, Finding::TYPE_LATE, null, Carbon::parse('2026-09-10'));
    SlaInstance::create([
        'finding_id' => $finding->id,
        'responsible_user_id' => $manager->id,
        'sla_type' => SlaInstance::TYPE_ISO,
        'effective_minutes' => 240,
        'started_at' => now(),
        'due_at' => now()->addHours(4),
        'status' => SlaInstance::STATUS_BREACHED,
        'breached_at' => Carbon::parse('2026-09-11'),
    ]);

    $result = app(ComplianceScoreService::class)->managerScore($manager, Carbon::parse('2026-09-15'));

    expect($result)->toBe(['score' => 100.0, 'deduction_count' => 0, 'locked' => false]);
});

test('a locked month returns its stored score verbatim, ignoring current data', function () {
    $pic = makeUserWithRoles([Role::PIC]);
    makeAutomaticFinding($pic, Finding::TYPE_LATE, null, Carbon::parse('2026-09-10'));

    MonthlyScore::create([
        'subject_type' => MonthlyScore::SUBJECT_PIC,
        'subject_id' => $pic->id,
        'period_month' => '2026-09-01',
        'score' => 100.0,
        'deduction_count' => 0,
        'locked_at' => now(),
    ]);

    $result = app(ComplianceScoreService::class)->picScore($pic, Carbon::parse('2026-09-20'));

    expect($result)->toBe(['score' => 100.0, 'deduction_count' => 0, 'locked' => true]);
});

test('lockMonth creates a row per pic and per manager and is idempotent', function () {
    $pic = makeUserWithRoles([Role::PIC]);
    $manager = makeManager(Department::factory()->create());
    makeAutomaticFinding($pic, Finding::TYPE_LATE, null, Carbon::parse('2026-09-10'));

    $service = app(ComplianceScoreService::class);
    $first = $service->lockMonth(Carbon::parse('2026-09-01'));

    expect($first['pic_count'])->toBe(1);
    expect($first['manager_count'])->toBe(1);
    expect(MonthlyScore::where('subject_id', $pic->id)->first()->score)->toEqualWithDelta(95.0, 0.001);

    $second = $service->lockMonth(Carbon::parse('2026-09-01'));

    expect($second['pic_count'])->toBe(0);
    expect($second['manager_count'])->toBe(0);
    expect(MonthlyScore::count())->toBe(2);
});
