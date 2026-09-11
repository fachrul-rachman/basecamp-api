<?php

use App\Models\Holiday;
use App\Models\WorkingCalendar;
use App\Services\Scheduling\WorkingTimeCalculator;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->calculator = new WorkingTimeCalculator;
    $this->calendar = WorkingCalendar::factory()->withOfficeHours()->create();
    $this->calendar->load(['hours', 'exceptions']);

    // A known Monday, so weekday-based fixtures are deterministic.
    $this->monday = Carbon::parse('next monday')->startOfDay();
    $this->saturday = $this->monday->copy()->addDays(5);
});

test('a moment within weekday office hours is working time', function () {
    $moment = $this->monday->copy()->setTime(10, 0);

    expect($this->calculator->isWorking($this->calendar, $moment))->toBeTrue();
});

test('a moment before office hours is not working time', function () {
    $moment = $this->monday->copy()->setTime(7, 0);

    expect($this->calculator->isWorking($this->calendar, $moment))->toBeFalse();
});

test('a moment after office hours is not working time', function () {
    $moment = $this->monday->copy()->setTime(18, 0);

    expect($this->calculator->isWorking($this->calendar, $moment))->toBeFalse();
});

test('a weekend moment is not working time', function () {
    $moment = $this->saturday->copy()->setTime(10, 0);

    expect($this->calculator->isWorking($this->calendar, $moment))->toBeFalse();
});

test('a calendar exception marking a normally-working day off overrides the weekly hours', function () {
    $this->calendar->exceptions()->create([
        'date' => $this->monday->toDateString(),
        'is_working' => false,
        'reason' => 'Public holiday observed',
    ]);
    $this->calendar->load('exceptions');

    $moment = $this->monday->copy()->setTime(10, 0);

    expect($this->calculator->isWorking($this->calendar, $moment))->toBeFalse();
});

test('a calendar exception can extend work to a normally non-working day', function () {
    $this->calendar->exceptions()->create([
        'date' => $this->saturday->toDateString(),
        'is_working' => true,
        'start_time' => '09:00',
        'end_time' => '12:00',
    ]);
    $this->calendar->load('exceptions');

    $moment = $this->saturday->copy()->setTime(10, 0);

    expect($this->calculator->isWorking($this->calendar, $moment))->toBeTrue();
});

test('a holiday on a normally-working day is not working time', function () {
    Holiday::factory()->create(['date' => $this->monday->toDateString()]);

    $moment = $this->monday->copy()->setTime(10, 0);

    expect($this->calculator->isWorking($this->calendar, $moment))->toBeFalse();
});

test('adding working minutes that fit within the same day does not roll over', function () {
    $start = $this->monday->copy()->setTime(8, 0);

    $result = $this->calculator->addWorkingMinutes($this->calendar, $start, 60);

    expect($result->toDateTimeString())->toBe($this->monday->copy()->setTime(9, 0)->toDateTimeString());
});

test('adding working minutes pauses overnight and resumes the next working day', function () {
    $start = $this->monday->copy()->setTime(16, 0);

    $result = $this->calculator->addWorkingMinutes($this->calendar, $start, 120);

    // 60 minutes left in Monday (16:00-17:00), then 60 more minutes into Tuesday morning.
    $expected = $this->monday->copy()->addDay()->setTime(9, 0);
    expect($result->toDateTimeString())->toBe($expected->toDateTimeString());
});

test('adding working minutes skips the weekend entirely', function () {
    $friday = $this->monday->copy()->addDays(4);
    $start = $friday->copy()->setTime(16, 30);

    $result = $this->calculator->addWorkingMinutes($this->calendar, $start, 90);

    // 30 minutes left in Friday (16:30-17:00), then 60 more minutes into next Monday morning.
    $expected = $this->monday->copy()->addDays(7)->setTime(9, 0);
    expect($result->toDateTimeString())->toBe($expected->toDateTimeString());
});
