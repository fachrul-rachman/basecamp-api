<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('user'));
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('user'))],
            'is_active' => ['sometimes', 'boolean'],
            'role_codes' => ['sometimes', 'array'],
            'role_codes.*' => ['string', Rule::exists('roles', 'code')],
            'department_ids' => ['sometimes', 'array'],
            'department_ids.*' => ['uuid', Rule::exists('departments', 'id')],
            'primary_department_id' => ['sometimes', 'nullable', 'uuid'],
        ];
    }
}
