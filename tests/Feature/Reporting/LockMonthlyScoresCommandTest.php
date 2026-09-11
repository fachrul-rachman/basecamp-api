<?php

use App\Models\Finding;
use App\Models\MonthlyScore;
use App\Models\Role;
use Illuminate\Support\Carbon;

test('the scores:lock-month command locks the given month and reports a summary', function () {
    $pic = makeUserWithRoles([Role::PIC]);
    makeAutomaticFinding($pic, Finding::TYPE_LATE, null, Carbon::parse('2026-09-10'));

    $this->artisan('scores:lock-month', ['--month' => '2026-09'])
        ->expectsOutputToContain('Locked 1 PIC score(s)')
        ->assertExitCode(0);

    expect(MonthlyScore::where('subject_id', $pic->id)->whereDate('period_month', '2026-09-01')->exists())->toBeTrue();
});

test('the scores:lock-month command defaults to last month when no option is given', function () {
    $pic = makeUserWithRoles([Role::PIC]);
    $lastMonth = now()->subMonthNoOverflow()->startOfMonth();
    makeAutomaticFinding($pic, Finding::TYPE_LATE, null, $lastMonth->copy()->addDays(2));

    $this->artisan('scores:lock-month')->assertExitCode(0);

    expect(MonthlyScore::where('subject_id', $pic->id)->whereDate('period_month', $lastMonth->toDateString())->exists())->toBeTrue();
});

test('scores:lock-month resolves the --month option to the correct month even when today is the 31st', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-31'));

    $pic = makeUserWithRoles([Role::PIC]);
    makeAutomaticFinding($pic, Finding::TYPE_LATE, null, Carbon::parse('2026-04-15'));

    $this->artisan('scores:lock-month', ['--month' => '2026-04'])->assertExitCode(0);

    expect(MonthlyScore::where('subject_id', $pic->id)->whereDate('period_month', '2026-04-01')->exists())->toBeTrue();

    Carbon::setTestNow();
});
