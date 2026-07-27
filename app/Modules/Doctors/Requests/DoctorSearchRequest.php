<?php

namespace App\Modules\Doctors\Requests;

use Illuminate\Foundation\Http\FormRequest;


class DoctorSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'department_id' => ['sometimes', 'integer', 'exists:departments,id'],
            'clinic_id'     => ['sometimes', 'integer', 'exists:clinics,id'],
            'name'          => ['sometimes', 'string', 'max:100'],
            'per_page'      => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }
}
