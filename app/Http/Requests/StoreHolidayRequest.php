<?php

namespace App\Http\Requests;

use App\Models\Holiday;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Holiday::class);
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'name' => ['required', 'string', 'max:255'],
            'scope' => ['required', Rule::in([Holiday::SCOPE_COMPANY, Holiday::SCOPE_NATIONAL])],
        ];
    }
}
