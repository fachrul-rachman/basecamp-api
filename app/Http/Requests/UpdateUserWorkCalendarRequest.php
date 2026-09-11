<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserWorkCalendarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('user'));
    }

    public function rules(): array
    {
        return [
            'working_calendar_id' => ['nullable', 'uuid', 'exists:working_calendars,id'],
        ];
    }
}
