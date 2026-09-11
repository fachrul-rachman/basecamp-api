<?php

namespace App\Services\Scheduling;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Resolves available_at/deadline_at/failure_at for one operational_date
 * from a checklist's start_time/end_time (docs/02-BUSINESS-RULES.md §3).
 *
 * `end_time` is the nominal deadline. `failure_at` extends to the end of
 * the operational day for a same-day window (giving a late window from
 * end_time to midnight); for a window crossing midnight, `end_time` is
 * already on the next calendar day, so `failure_at` equals `deadline_at`
 * there (no separate late window in that case).
 */
class OperationalWindowResolver
{
    /**
     * @return array{available_at: CarbonInterface, deadline_at: CarbonInterface, failure_at: CarbonInterface}
     */
    public function resolve(CarbonInterface $operationalDate, string $startTime, string $endTime): array
    {
        $date = $operationalDate->toDateString();
        $availableAt = Carbon::parse("{$date} {$startTime}");
        $crossesMidnight = $endTime < $startTime;

        if ($crossesMidnight) {
            $deadlineAt = Carbon::parse($date)->addDay()->setTimeFromTimeString($endTime);
            $failureAt = $deadlineAt->copy();
        } else {
            $deadlineAt = Carbon::parse("{$date} {$endTime}");
            $failureAt = Carbon::parse($date)->addDay()->startOfDay();
        }

        return [
            'available_at' => $availableAt,
            'deadline_at' => $deadlineAt,
            'failure_at' => $failureAt,
        ];
    }
}
