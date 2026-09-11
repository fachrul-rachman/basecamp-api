<?php

use Illuminate\Console\Scheduling\Schedule;

test('every scheduled command guards against overlapping runs', function () {
    $ourCommands = ['work-items:generate', 'work-items:evaluate', 'findings:evaluate-sla', 'holidays:sync', 'scores:lock-month'];

    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event) => collect($ourCommands)->contains(fn ($name) => str_contains($event->command ?? '', $name)));

    expect($events)->toHaveCount(5);

    $events->each(fn ($event) => expect($event->withoutOverlapping)->toBeTrue());
});
