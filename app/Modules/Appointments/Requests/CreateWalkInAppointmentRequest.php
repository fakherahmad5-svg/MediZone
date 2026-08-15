<?php

namespace App\Modules\Appointments\Requests;

use App\Core\Enums\ConsultationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;


class CreateWalkInAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_id'     => ['required', 'integer', 'exists:patients,id'],
            'doctor_id'      => ['required', 'integer', 'exists:doctors,id'],
            'encounter_type' => ['required', 'string', Rule::in(ConsultationType::values())],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ];
    }
}
