<?php

namespace App\Modules\Scheduling\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateSlotsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date_from' => ['required', 'date', 'after_or_equal:today'],
            'date_to'   => ['required', 'date', 'after_or_equal:date_from'],
        ];
    }
}
