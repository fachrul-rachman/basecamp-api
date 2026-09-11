<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDepartmentMembersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageMembers', $this->route('department'));
    }

    public function rules(): array
    {
        return [
            'members' => ['present', 'array'],
            'members.*.user_id' => ['required', 'uuid', 'exists:users,id'],
            'members.*.is_primary' => ['sometimes', 'boolean'],
        ];
    }
}
