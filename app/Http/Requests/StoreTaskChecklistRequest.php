<?php

namespace App\Http\Requests;

use App\Support\Scheduling\ScheduleConfigRules;
use App\Support\Scheduling\ScheduleType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskChecklistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageChecklists', $this->route('task'));
    }

    public function rules(): array
    {
        return array_merge([
            'title' => ['required', 'string', 'max:255'],
            'instructions' => ['nullable', 'string'],
            'target_department_id' => ['nullable', 'uuid', Rule::exists('departments', 'id')],
            'schedule_type' => ['required', Rule::in(ScheduleType::values())],
            'evidence_min_count' => ['sometimes', 'integer', 'min:0'],
            'allow_upload' => ['sometimes', 'boolean'],
            'allow_camera' => ['sometimes', 'boolean'],
            'works_on_holidays' => ['sometimes', 'boolean'],
        ], ScheduleConfigRules::rules('schedule_type', 'schedule_config'));
    }
}
