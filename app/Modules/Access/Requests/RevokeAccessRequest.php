<?php

namespace App\Modules\Access\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RevokeAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // permission checked explicitly in controller (matches Handoff's admin-controller pattern)
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
