<?php

namespace App\Http\Requests;

use App\Models\PicLeaveRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePicLeaveRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', PicLeaveRequest::class);
    }

    public function rules(): array
    {
        return [
            'pic_id' => [
                'required', 'uuid', Rule::exists('users', 'id'),
                function ($attribute, $value, $fail) {
                    $pic = User::with(['departments', 'roles'])->find($value);
                    $actor = $this->user();

                    if (! $pic || ! $pic->hasRole(Role::PIC)) {
                        $fail('The selected user must hold the PIC role.');

                        return;
                    }

                    if ($actor->departments->pluck('id')->intersect($pic->departments->pluck('id'))->isEmpty()) {
                        $fail('The selected PIC must belong to a department you manage.');
                    }
                },
            ],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'reason' => ['required', 'string'],
            'evidence' => ['nullable', 'file', 'max:10240'],
        ];
    }
}
