<?php

use App\Models\WorkItem;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;

test('work-items:generate with no --date option generates for both today and tomorrow', function () {
    // Frozen to a morning hour so today's default 08:00-17:00 window is
    // still open regardless of what time this suite actually runs at
    // (the generator now skips creating an already-elapsed today window).
    Carbon::setTestNow(Carbon::now()->startOfDay()->addHours(9));

    [, $checklistToday] = makeTaskWithChecklist(['schedule_type' => 'daily'], ['attributes' => ['starts_at' => now()->subDay()]]);
    [, $checklistTomorrow] = makeTaskWithChecklist(['schedule_type' => 'daily'], ['attributes' => ['starts_at' => now()]]);

    $this->artisan('work-items:generate')->assertExitCode(0);

    expect(WorkItem::where('task_checklist_id', $checklistToday->id)->whereDate('operational_date', now()->toDateString())->exists())->toBeTrue();
    expect(WorkItem::where('task_checklist_id', $checklistTomorrow->id)->whereDate('operational_date', now()->addDay()->toDateString())->exists())->toBeTrue();

    Carbon::setTestNow();
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

test('does not generate a Work Item for today when the checklist window has already fully elapsed', function () {
    Carbon::setTestNow(Carbon::now()->startOfDay()->addHours(18));

    [, $elapsedChecklist] = makeTaskWithChecklist(
        ['schedule_type' => 'daily', 'schedule_config' => ['start_time' => '08:00', 'end_time' => '17:00']],
        ['attributes' => ['starts_at' => now()->subDay()]]
    );
    [, $openChecklist] = makeTaskWithChecklist(
        ['schedule_type' => 'daily', 'schedule_config' => ['start_time' => '08:00', 'end_time' => '23:00']],
        ['attributes' => ['starts_at' => now()->subDay()]]
    );

    $this->artisan('work-items:generate')->assertExitCode(0);

    expect(WorkItem::where('task_checklist_id', $elapsedChecklist->id)->whereDate('operational_date', now()->toDateString())->exists())->toBeFalse();
    expect(WorkItem::where('task_checklist_id', $openChecklist->id)->whereDate('operational_date', now()->toDateString())->exists())->toBeTrue();

    Carbon::setTestNow();
});
