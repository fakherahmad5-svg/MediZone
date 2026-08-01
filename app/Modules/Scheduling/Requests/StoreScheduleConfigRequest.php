<?php

namespace App\Modules\Scheduling\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreScheduleConfigRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'clinic_id'             => ['required', 'exists:clinics,id'],
            'consultation_duration' => ['required', 'integer', 'min:5', 'max:120'],
            'break_duration'        => ['nullable', 'integer', 'min:0', 'max:60'],
            'max_patients'          => ['nullable', 'integer', 'min:1'],
            'buffer_enabled'        => ['nullable', 'boolean'],
        ];
    }
}
