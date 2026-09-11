<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RescheduleTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('reschedule', $this->route('task'));
    }

    public function rules(): array
    {
        return [
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
