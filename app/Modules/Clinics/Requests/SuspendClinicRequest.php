<?php

namespace App\Modules\Clinics\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SuspendClinicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
