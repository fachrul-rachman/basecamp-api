<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFindingExplanationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('respond', $this->route('finding'));
    }

    public function rules(): array
    {
        return [
            'notes' => ['required', 'string'],
        ];
    }
}
