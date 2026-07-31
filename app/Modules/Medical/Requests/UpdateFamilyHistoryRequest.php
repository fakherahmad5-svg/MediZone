<?php

namespace App\Modules\Medical\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFamilyHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'condition' => ['sometimes', 'string', 'max:150'],
            'relation'  => ['sometimes', 'string', 'max:50'],
            'notes'     => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
