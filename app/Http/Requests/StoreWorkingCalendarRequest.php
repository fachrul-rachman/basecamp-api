<?php

namespace App\Http\Requests;

use App\Models\WorkingCalendar;
use Illuminate\Foundation\Http\FormRequest;

class StoreWorkingCalendarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', WorkingCalendar::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'timezone' => ['sometimes', 'string', 'max:64'],
            'is_active' => ['sometimes', 'boolean'],
            'hours' => ['sometimes', 'array'],
            'hours.*.weekday' => ['required_with:hours', 'integer', 'between:0,6'],
            'hours.*.is_working_day' => ['sometimes', 'boolean'],
            'hours.*.start_time' => ['nullable', 'date_format:H:i'],
            'hours.*.end_time' => ['nullable', 'date_format:H:i', 'after:hours.*.start_time'],
        ];
    }
}
