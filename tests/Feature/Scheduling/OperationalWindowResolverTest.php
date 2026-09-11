<?php

use App\Services\Scheduling\OperationalWindowResolver;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->resolver = new OperationalWindowResolver;
});

test('a same-day window sets the deadline at end_time and failure at midnight', function () {
    $date = Carbon::parse('2026-09-07');

    $window = $this->resolver->resolve($date, '08:00', '17:00');

    expect($window['available_at']->toDateTimeString())->toBe('2026-09-07 08:00:00');
    expect($window['deadline_at']->toDateTimeString())->toBe('2026-09-07 17:00:00');
    expect($window['failure_at']->toDateTimeString())->toBe('2026-09-08 00:00:00');
});

test('a window crossing midnight sets deadline and failure to the same next-day moment', function () {
    $date = Carbon::parse('2026-09-07');

    $window = $this->resolver->resolve($date, '22:00', '06:00');

    expect($window['available_at']->toDateTimeString())->toBe('2026-09-07 22:00:00');
    expect($window['deadline_at']->toDateTimeString())->toBe('2026-09-08 06:00:00');
    expect($window['failure_at']->toDateTimeString())->toBe('2026-09-08 06:00:00');
});
