<?php

namespace App\Modules\Medical\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSurgeryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'surgery_name' => ['required', 'string', 'max:150'],
            'surgery_date' => ['nullable', 'date', 'before_or_equal:today'],
            'notes'        => ['nullable', 'string', 'max:1000'],
        ];
    }
}
