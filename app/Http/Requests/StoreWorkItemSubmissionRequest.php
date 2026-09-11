<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkItemSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('submit', $this->route('workItem'));
    }

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string'],
            'submit' => ['sometimes', 'boolean'],
        ];
    }
}
