<?php

namespace App\Http\Requests;

use App\Support\Scheduling\ScheduleConfigRules;
use App\Support\Scheduling\ScheduleType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('template'));
    }

    public function rules(): array
    {
        return array_merge([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'department_ids' => ['sometimes', 'array'],
            'department_ids.*' => ['uuid', Rule::exists('departments', 'id')],
            'checklists' => ['sometimes', 'array'],
            'checklists.*.id' => [
                'sometimes', 'uuid',
                Rule::exists('task_template_checklists', 'id')->where('task_template_id', $this->route('template')?->id),
            ],
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
