<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReviewPicLeaveRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('review', $this->route('picLeaveRequest'));
    }

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string'],
        ];
    }
}
