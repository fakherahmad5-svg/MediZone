<?php

namespace App\Modules\Medical\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreChronicConditionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'condition_name' => ['required', 'string', 'max:150'],
            'diagnosed_at'   => ['nullable', 'date', 'before_or_equal:today'],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ];
    }
}
