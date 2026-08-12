<?php

namespace App\Modules\Scheduling\Requests;

use Illuminate\Foundation\Http\FormRequest;


class AvailabilityQueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'clinic_id' => ['sometimes', 'integer', 'exists:clinics,id'],
            'date_from' => ['sometimes', 'date', 'after_or_equal:today'],
            'date_to'   => ['sometimes', 'date', 'after_or_equal:date_from'],
        ];
    }
}
