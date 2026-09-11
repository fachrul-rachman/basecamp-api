<?php

namespace App\Http\Requests;

use App\Models\Evidence;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkItemEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('submit', $this->route('workItem'));
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:10240'],
            'source_type' => ['required', Rule::in([Evidence::SOURCE_UPLOAD, Evidence::SOURCE_CAMERA])],
            'captured_at' => ['nullable', 'date'],
        ];
    }
}
