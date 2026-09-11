<?php

namespace App\Http\Requests;

use App\Models\Holiday;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('holiday'));
    }

    public function rules(): array
    {
        return [
            'date' => ['sometimes', 'date'],
            'name' => ['sometimes', 'string', 'max:255'],
            'scope' => ['sometimes', Rule::in([Holiday::SCOPE_COMPANY, Holiday::SCOPE_NATIONAL])],
        ];
    }
}
