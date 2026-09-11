<?php

namespace App\Services\Scheduling;

use App\Models\Holiday;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Computes schedule-conflict warnings for a Task's date range (see
 * docs/02-BUSINESS-RULES.md §10: "Manager receives schedule-conflict
 * warnings when task range overlaps relevant holidays or assignee
 * non-working days... Warnings do not automatically cancel the task.").
 *
 * Phase 4 only has holiday/assignee-calendar checks available; there is no
 * assignee concept yet at Task/Checklist level (that arrives with Work
 * Item assignment in Phase 5/6), so callers only pass an assignee when one
 * is actually known (e.g. the `/calendar-conflicts` helper endpoint).
 */
class ScheduleConflictChecker
{
    public function __construct(private WorkingTimeCalculator $calculator) {}

    /**
     * @return array<int, array{type: string, date: string, message: string}>
     */
    public function check(CarbonInterface $start, CarbonInterface $end, bool $worksOnHolidays, ?User $assignee = null): array
    {
        $warnings = [];

        if (! $worksOnHolidays) {
            $holidays = Holiday::query()
                ->whereDate('date', '>=', $start->toDateString())
                ->whereDate('date', '<=', $end->toDateString())
                ->get();

            foreach ($holidays as $holiday) {
                $warnings[] = [
                    'type' => 'holiday',
                    'date' => $holiday->date->toDateString(),
                    'message' => "Overlaps holiday: {$holiday->name} ({$holiday->date->toDateString()}).",
                ];
            }
        }

        if ($assignee && $assignee->workingCalendar) {
            $calendar = $assignee->workingCalendar->loadMissing(['hours', 'exceptions']);
            $cursor = $start->copy()->startOfDay();
            $lastDay = $end->copy()->startOfDay();

            // ponytail: bounded day-by-day scan, fine at task-range scale;
            // revisit if tasks routinely span many months.
            for ($i = 0; $i < 366 && $cursor->lte($lastDay); $i++) {
                if (! $this->calculator->hasWorkingTimeOn($calendar, $cursor)) {
                    $warnings[] = [
                        'type' => 'assignee_non_working_day',
                        'date' => $cursor->toDateString(),
                        'message' => "Assignee is not scheduled to work on {$cursor->toDateString()}.",
                    ];
                }
                $cursor = $cursor->addDay();
            }
        }

        return $warnings;
    }
}
