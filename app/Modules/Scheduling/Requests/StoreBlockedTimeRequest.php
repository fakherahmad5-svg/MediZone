<?php

namespace App\Modules\Scheduling\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBlockedTimeRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'clinic_id'   => ['required', 'exists:clinics,id'],
            'block_date'  => ['nullable', 'date', 'date_format:Y-m-d'],
            'day_of_week' => ['nullable', 'integer', 'min:0', 'max:6'],
            'start_time'  => ['required', 'date_format:H:i'],
            'end_time'    => ['required', 'date_format:H:i', 'after:start_time'],
            'reason'      => ['nullable', 'string', 'max:255'],
        ];
    }
}
