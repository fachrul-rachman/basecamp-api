<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IsoReviewFindingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('review', $this->route('finding'));
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['accept', 'reject'])],
            'notes' => ['nullable', 'string'],
        ];
    }
}
