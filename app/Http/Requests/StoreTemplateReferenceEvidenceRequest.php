<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTemplateReferenceEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageReferenceEvidence', $this->route('template'));
    }

    public function rules(): array
    {
        return [
            // docs/06-API-CONTRACT.md §5 nests this endpoint under the
            // template but docs/05-DATABASE-SCHEMA.md §14 ties evidence to
            // a specific checklist; this field reconciles the two by
            // requiring the checklist explicitly, scoped to this template.
            'task_template_checklist_id' => [
                'required', 'uuid',
                Rule::exists('task_template_checklists', 'id')->where('task_template_id', $this->route('template')?->id),
            ],
            'file' => ['required', 'file', 'image', 'max:5120'],
        ];
    }
}
