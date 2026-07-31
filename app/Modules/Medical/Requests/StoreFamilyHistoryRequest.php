<?php

namespace App\Modules\Medical\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFamilyHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'condition' => ['required', 'string', 'max:150'],
            'relation'  => ['required', 'string', 'max:50'],
            'notes'     => ['nullable', 'string', 'max:1000'],
        ];
    }
}
