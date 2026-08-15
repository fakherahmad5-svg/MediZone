<?php

namespace App\Modules\Appointments\Requests;

use App\Core\Enums\ConsultationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;


class BookAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'slot_id'        => ['required', 'integer', 'exists:doctor_time_slots,id'],
            'encounter_type' => ['required', 'string', Rule::in(ConsultationType::values())],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ];
    }
}
