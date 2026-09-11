<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignDepartmentRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('respond', $this->route('departmentRequest'));
    }

    public function rules(): array
    {
        return [
            'pic_id' => ['required', 'uuid', Rule::exists('users', 'id')],
        ];
    }
}
