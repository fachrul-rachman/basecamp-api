<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', User::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'is_active' => ['sometimes', 'boolean'],
            'role_codes' => ['sometimes', 'array'],
            'role_codes.*' => ['string', Rule::exists('roles', 'code')],
            'department_ids' => ['sometimes', 'array'],
            'department_ids.*' => ['uuid', Rule::exists('departments', 'id')],
            'primary_department_id' => ['sometimes', 'nullable', 'uuid'],
        ];
    }
}
