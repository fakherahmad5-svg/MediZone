<?php

namespace App\Modules\Medical\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateChronicConditionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'condition_name' => ['sometimes', 'string', 'max:150'],
            'diagnosed_at'   => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
            'notes'          => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
