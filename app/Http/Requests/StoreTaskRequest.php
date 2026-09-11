<?php

namespace App\Http\Requests;

use App\Models\Task;
use App\Support\Scheduling\ScheduleConfigRules;
use App\Support\Scheduling\ScheduleType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [Task::class, $this->input('owner_department_id')]);
    }

    public function rules(): array
    {
        return array_merge([
            'template_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('task_templates', 'id')],
            'owner_department_id' => ['required', 'uuid', Rule::exists('departments', 'id')],
            'title' => ['required_without:template_id', 'nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'overall_deadline_at' => ['nullable', 'date'],
            'checklists' => ['sometimes', 'array'],
            'checklists.*.title' => ['required', 'string', 'max:255'],
            'checklists.*.instructions' => ['nullable', 'string'],
            'checklists.*.target_department_id' => ['nullable', 'uuid', Rule::exists('departments', 'id')],
            'checklists.*.schedule_type' => ['required', Rule::in(ScheduleType::values())],
            'checklists.*.evidence_min_count' => ['sometimes', 'integer', 'min:0'],
            'checklists.*.allow_upload' => ['sometimes', 'boolean'],
            'checklists.*.allow_camera' => ['sometimes', 'boolean'],
            'checklists.*.works_on_holidays' => ['sometimes', 'boolean'],
        ], ScheduleConfigRules::rules('checklists.*.schedule_type', 'checklists.*.schedule_config'));
    }
}
