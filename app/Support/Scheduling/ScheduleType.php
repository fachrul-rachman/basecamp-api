<?php

namespace App\Support\Scheduling;

/**
 * Scheduling models required by docs/02-BUSINESS-RULES.md §2. No checklist
 * dependency engine is required, and this is intentionally a closed set,
 * not a generic rule engine (see docs/08-BACKEND-ARCHITECTURE.md §2).
 */
enum ScheduleType: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case WeeklyQuota = 'weekly_quota';
    case Monthly = 'monthly';
    case OneTime = 'one_time';
    case Event = 'event';

    /**
     * @return string[]
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
