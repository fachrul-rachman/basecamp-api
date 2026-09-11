<?php

use App\Models\MonthlyScore;
use Illuminate\Database\QueryException;

test('a monthly score can be created and read back with the right casts', function () {
    [$item, $pic] = makeAvailableWorkItem();

    $score = MonthlyScore::create([
        'subject_type' => MonthlyScore::SUBJECT_PIC,
        'subject_id' => $pic->id,
        'period_month' => '2026-09-01',
        'score' => 85,
        'deduction_count' => 3,
        'locked_at' => now(),
    ]);

    $fresh = $score->fresh();

    expect($fresh->period_month->toDateString())->toBe('2026-09-01');
    expect((float) $fresh->score)->toBe(85.0);
    expect($fresh->deduction_count)->toBe(3);
    expect($fresh->subject->id)->toBe($pic->id);
});

test('the same subject cannot get two locked scores for the same month', function () {
    [$item, $pic] = makeAvailableWorkItem();

    MonthlyScore::create([
        'subject_type' => MonthlyScore::SUBJECT_PIC,
        'subject_id' => $pic->id,
        'period_month' => '2026-09-01',
        'score' => 100,
        'deduction_count' => 0,
        'locked_at' => now(),
    ]);

    expect(fn () => MonthlyScore::create([
        'subject_type' => MonthlyScore::SUBJECT_PIC,
        'subject_id' => $pic->id,
        'period_month' => '2026-09-01',
        'score' => 90,
        'deduction_count' => 1,
        'locked_at' => now(),
    ]))->toThrow(QueryException::class);
});
