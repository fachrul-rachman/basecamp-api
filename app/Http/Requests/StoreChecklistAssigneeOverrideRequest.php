<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChecklistAssigneeOverrideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageChecklists', $this->route('task'));
    }

    public function rules(): array
    {
        return [
            'pic_id' => ['required', 'uuid', Rule::exists('users', 'id')],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
        ];
    }
}
