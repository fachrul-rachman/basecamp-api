<?php

namespace App\Support\Scheduling;

use Illuminate\Validation\Rule;

/**
 * Validates the shape of `schedule_config` per `schedule_type`
 * (docs/05-DATABASE-SCHEMA.md §13: "schedule_config is acceptable because
 * recurrence shapes vary; keep its documented schema validated in
 * application code. Do not make arbitrary user-defined expressions.").
 *
 * Shared between Template checklists (Phase 3) and Task checklists
 * (Phase 4) so both validate against the exact same shape.
 */
class ScheduleConfigRules
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(string $typeField, string $configPrefix): array
    {
        $timedTypes = implode(',', [
            ScheduleType::Daily->value,
            ScheduleType::Weekly->value,
            ScheduleType::WeeklyQuota->value,
            ScheduleType::Monthly->value,
            ScheduleType::OneTime->value,
        ]);

        return [
            "{$configPrefix}.start_time" => ["required_if:{$typeField},{$timedTypes}", 'date_format:H:i'],
            "{$configPrefix}.end_time" => ["required_if:{$typeField},{$timedTypes}", 'date_format:H:i'],

            "{$configPrefix}.weekdays" => ["required_if:{$typeField},".ScheduleType::Weekly->value, 'array', 'min:1'],
            "{$configPrefix}.weekdays.*" => ['integer', 'between:0,6'],

            "{$configPrefix}.period" => ["required_if:{$typeField},".ScheduleType::WeeklyQuota->value, Rule::in(['week', 'month'])],
            "{$configPrefix}.target_count" => ["required_if:{$typeField},".ScheduleType::WeeklyQuota->value, 'integer', 'min:1'],

            "{$configPrefix}.day_of_month" => ["required_if:{$typeField},".ScheduleType::Monthly->value, 'integer', 'between:1,31'],

            "{$configPrefix}.date" => ["required_if:{$typeField},".ScheduleType::OneTime->value, 'date'],
            "{$configPrefix}.end_date" => ['nullable', 'date', "after_or_equal:{$configPrefix}.date"],

            "{$configPrefix}.response_window_hours" => ["required_if:{$typeField},".ScheduleType::Event->value, 'integer', 'min:1'],
        ];
    }
}
