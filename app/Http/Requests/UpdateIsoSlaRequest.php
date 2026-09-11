<?php

namespace App\Http\Requests;

use App\Models\SlaSetting;
use Illuminate\Foundation\Http\FormRequest;

class UpdateIsoSlaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageIsoScope', SlaSetting::class);
    }

    public function rules(): array
    {
        return [
            // No override chain exists for ISO SLA, so unlike the Manager
            // scopes there is nothing to fall back to if cleared.
            'minutes' => ['required', 'integer', 'min:1'],
        ];
    }
}
