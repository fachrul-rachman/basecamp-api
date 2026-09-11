<?php

namespace App\Http\Requests;

use App\Models\SlaSetting;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDepartmentSlaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageManagerScope', SlaSetting::class);
    }

    public function rules(): array
    {
        return [
            'minutes' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
