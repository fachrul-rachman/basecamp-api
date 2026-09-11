<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('reviewEvidence', $this->route('workItem'));
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['confirmed', 'dismissed'])],
        ];
    }
}
