<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCalendarExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageExceptions', $this->route('workingCalendar'));
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'is_working' => ['sometimes', 'boolean'],
            'start_time' => ['nullable', 'required_if:is_working,true', 'date_format:H:i'],
            'end_time' => ['nullable', 'required_if:is_working,true', 'date_format:H:i'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
