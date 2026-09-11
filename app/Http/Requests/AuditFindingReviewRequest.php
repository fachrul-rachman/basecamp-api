<?php

namespace App\Http\Requests;

use App\Models\Finding;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AuditFindingReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('review', $this->route('finding'));
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([Finding::STATUS_OPEN, Finding::STATUS_CLOSED, Finding::STATUS_INFO])],
            'notes' => ['nullable', 'string'],
            'due_at' => ['nullable', 'date'],
        ];
    }
}
