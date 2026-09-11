<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReopenWorkItemFindingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('respond', $this->route('finding'));
    }

    public function rules(): array
    {
        return [
            'deadline_at' => ['required', 'date', 'after:now'],
            'reason' => ['required', 'string'],
        ];
    }
}
