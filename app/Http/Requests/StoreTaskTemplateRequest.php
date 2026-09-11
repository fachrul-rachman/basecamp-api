<?php

namespace App\Http\Requests;

use App\Models\TaskTemplate;
use App\Support\Scheduling\ScheduleConfigRules;
use App\Support\Scheduling\ScheduleType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', TaskTemplate::class);
    }

    public function rules(): array
    {
        return array_merge([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'department_ids' => ['sometimes', 'array'],
            'department_ids.*' => ['uuid', Rule::exists('departments', 'id')],
            'checklists' => ['sometimes', 'array'],
            'checklists.*.title' => ['required', 'string', 'max:255'],
            'checklists.*.instructions' => ['nullable', 'string'],
            'checklists.*.target_department_id' => ['nullable', 'uuid', Rule::exists('departments', 'id')],
            'checklists.*.schedule_type' => ['required', Rule::in(ScheduleType::values())],
            'checklists.*.evidence_min_count' => ['sometimes', 'integer', 'min:0'],
            'checklists.*.allow_upload' => ['sometimes', 'boolean'],
            'checklists.*.allow_camera' => ['sometimes', 'boolean'],
            'checklists.*.works_on_holidays' => ['sometimes', 'boolean'],
            'checklists.*.sort_order' => ['sometimes', 'integer', 'min:0'],
        ], ScheduleConfigRules::rules('checklists.*.schedule_type', 'checklists.*.schedule_config'));
    }
}
