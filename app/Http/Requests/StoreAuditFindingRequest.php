<?php

namespace App\Http\Requests;

use App\Models\Finding;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAuditFindingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Finding::class);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'target_department_id' => ['required', 'uuid', Rule::exists('departments', 'id')],
            'target_manager_id' => [
                'nullable', 'uuid', Rule::exists('users', 'id'),
                function ($attribute, $value, $fail) {
                    if (! $value) {
                        return;
                    }

                    $manager = User::with(['roles', 'departments'])->find($value);

                    if (! $manager
                        || ! $manager->hasRole(Role::MANAGER)
                        || ! $manager->departments->pluck('id')->contains($this->input('target_department_id'))
                    ) {
                        $fail('The selected manager must belong to the target department.');
                    }
                },
            ],
            'due_at' => ['nullable', 'date'],
            'status' => ['required', Rule::in([Finding::STATUS_OPEN, Finding::STATUS_INFO])],
            'task_id' => ['nullable', 'uuid', Rule::exists('tasks', 'id')],
            'task_checklist_id' => ['nullable', 'uuid', Rule::exists('task_checklists', 'id')],
            'work_item_id' => ['nullable', 'uuid', Rule::exists('work_items', 'id')],
            'source_evidence_id' => ['nullable', 'uuid', Rule::exists('evidence', 'id')],
        ];
    }
}
