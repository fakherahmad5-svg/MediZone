<?php

namespace App\Modules\Scheduling\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateScheduleConfigRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'consultation_duration' => ['sometimes', 'integer', 'min:5', 'max:120'],
            'break_duration'        => ['sometimes', 'integer', 'min:0', 'max:60'],
            'max_patients'          => ['sometimes', 'nullable', 'integer', 'min:1'],
            'buffer_enabled'        => ['sometimes', 'boolean'],
        ];
    }
}
