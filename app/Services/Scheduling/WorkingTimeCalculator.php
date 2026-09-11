<?php

namespace App\Services\Scheduling;

use App\Models\Holiday;
use App\Models\WorkingCalendar;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Resolves effective working time against a Working Calendar (weekly hours,
 * calendar exceptions, holidays) — see docs/02-BUSINESS-RULES.md §8-10 and
 * docs/03-DOMAIN-MODEL.md §14. Callers should eager-load the calendar's
 * `hours` and `exceptions` relations before use.
 */
class WorkingTimeCalculator
{
    public function isWorking(WorkingCalendar $calendar, CarbonInterface $moment): bool
    {
        $window = $this->resolveWindow($calendar, $moment);

        return $window !== null && $moment->gte($window['start']) && $moment->lt($window['end']);
    }

    /**
     * Whether this calendar has any working window at all on the given
     * date (day-level check, e.g. for schedule-conflict warnings — see
     * docs/02-BUSINESS-RULES.md §10).
     */
    public function hasWorkingTimeOn(WorkingCalendar $calendar, CarbonInterface $date): bool
    {
        return $this->resolveWindow($calendar, $date->copy()->startOfDay()) !== null;
    }

    /**
     * Add N effective working minutes to $start, skipping non-working time.
     * Used to resolve an SLA due date from effective minutes (see
     * docs/02-BUSINESS-RULES.md §8, §9).
     */
    public function addWorkingMinutes(WorkingCalendar $calendar, CarbonInterface $start, int $minutes): CarbonInterface
    {
        $cursor = $start->copy();
        $remaining = $minutes;

        // ponytail: day-window jumps, not a minute-by-minute tick — cheap
        // enough that a segment-based rewrite isn't worth it unless SLA
        // volumes make this a measured hotspot.
        for ($guard = 0; $guard < 3 * 366; $guard++) {
            $window = $this->resolveWindow($calendar, $cursor);

            if ($window === null || $cursor->gte($window['end'])) {
                $cursor = $cursor->copy()->addDay()->startOfDay();

                continue;
            }

            if ($cursor->lt($window['start'])) {
                $cursor = $window['start']->copy();
            }

            $availableMinutes = $cursor->diffInMinutes($window['end']);

            if ($remaining <= $availableMinutes) {
                return $cursor->copy()->addMinutes($remaining);
            }

            $remaining -= $availableMinutes;
            $cursor = $cursor->copy()->addDay()->startOfDay();
        }

        throw new RuntimeException('Could not resolve a working-time result within 3 years; check the working calendar configuration.');
    }

    /**
     * Effective working minutes elapsed between two moments (e.g. for
     * "remaining_work_minutes" on a running SLA — see
     * docs/06-API-CONTRACT.md §11). Symmetric to addWorkingMinutes().
     */
    public function elapsedWorkingMinutes(WorkingCalendar $calendar, CarbonInterface $start, CarbonInterface $end): int
    {
        if ($end->lte($start)) {
            return 0;
        }

        $cursor = $start->copy();
        $minutes = 0;

        for ($guard = 0; $guard < 3 * 366; $guard++) {
            if ($cursor->gte($end)) {
                break;
            }

            $window = $this->resolveWindow($calendar, $cursor);

            if ($window === null || $cursor->gte($window['end'])) {
                $cursor = $cursor->copy()->addDay()->startOfDay();

                continue;
            }

            $segmentStart = $cursor->lt($window['start']) ? $window['start']->copy() : $cursor->copy();
            $segmentEnd = $window['end']->lt($end) ? $window['end']->copy() : $end->copy();

            if ($segmentEnd->gt($segmentStart)) {
                $minutes += $segmentStart->diffInMinutes($segmentEnd);
            }

            $cursor = $cursor->copy()->addDay()->startOfDay();
        }

        return (int) $minutes;
    }

    /**
     * @return array{start: CarbonInterface, end: CarbonInterface}|null
     */
    private function resolveWindow(WorkingCalendar $calendar, CarbonInterface $moment): ?array
    {
        $date = $moment->toDateString();

        $exception = $calendar->exceptions->first(
            fn ($exception) => $exception->date->toDateString() === $date
        );

        if ($exception) {
            if (! $exception->is_working || ! $exception->start_time || ! $exception->end_time) {
                return null;
            }

            return $this->buildWindow($moment, $date, $exception->start_time, $exception->end_time);
        }

        if (Holiday::query()->whereDate('date', $date)->exists()) {
            return null;
        }

        $hour = $calendar->hours->first(
            fn ($hour) => (int) $hour->weekday === (int) $moment->dayOfWeek
        );

        if (! $hour || ! $hour->is_working_day || ! $hour->start_time || ! $hour->end_time) {
            return null;
        }

        return $this->buildWindow($moment, $date, $hour->start_time, $hour->end_time);
    }

    /**
     * @return array{start: CarbonInterface, end: CarbonInterface}
     */
    private function buildWindow(CarbonInterface $moment, string $date, string $startTime, string $endTime): array
    {
        return [
            'start' => Carbon::parse("{$date} {$startTime}", $moment->getTimezone()),
            'end' => Carbon::parse("{$date} {$endTime}", $moment->getTimezone()),
        ];
    }
}
