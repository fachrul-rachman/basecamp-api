<?php

use App\Models\WorkItem;
use Illuminate\Console\Scheduling\Schedule;

test('scores work-items:generate with no --date option generates for both today and tomorrow', function () {
    [, $checklistToday] = makeTaskWithChecklist(['schedule_type' => 'daily'], ['attributes' => ['starts_at' => now()->subDay()]]);
    [, $checklistTomorrow] = makeTaskWithChecklist(['schedule_type' => 'daily'], ['attributes' => ['starts_at' => now()]]);

    $this->artisan('work-items:generate')->assertExitCode(0);

    expect(WorkItem::where('task_checklist_id', $checklistToday->id)->whereDate('operational_date', now()->toDateString())->exists())->toBeTrue();
    expect(WorkItem::where('task_checklist_id', $checklistTomorrow->id)->whereDate('operational_date', now()->addDay()->toDateString())->exists())->toBeTrue();
});

test('work-items:generate --date still generates only for the given date', function () {
    [, $checklist] = makeTaskWithChecklist(['schedule_type' => 'daily'], ['attributes' => ['starts_at' => now()->subDay()]]);
    $targetDate = now()->addDays(5)->toDateString();

    $this->artisan('work-items:generate', ['--date' => $targetDate])->assertExitCode(0);

    expect(WorkItem::where('task_checklist_id', $checklist->id)->whereDate('operational_date', $targetDate)->exists())->toBeTrue();
    expect(WorkItem::where('task_checklist_id', $checklist->id)->whereDate('operational_date', now()->toDateString())->exists())->toBeFalse();
});

test('the work-items:generate schedule entry runs every 15 minutes with overlap protection', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event) => str_contains($event->command ?? '', 'work-items:generate'));

    expect($events)->toHaveCount(1);
    $event = $events->first();

    expect($event->withoutOverlapping)->toBeTrue();
    // everyFifteenMinutes() produces this exact cron expression.
    expect($event->expression)->toBe('*/15 * * * *');
});
