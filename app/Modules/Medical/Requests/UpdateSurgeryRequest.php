<?php

namespace App\Modules\Medical\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSurgeryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'surgery_name' => ['sometimes', 'string', 'max:150'],
            'surgery_date' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
            'notes'        => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
