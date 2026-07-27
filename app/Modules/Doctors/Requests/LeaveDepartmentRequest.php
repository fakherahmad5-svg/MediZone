<?php

namespace App\Modules\Doctors\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LeaveDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'clinic_id'     => ['required', 'integer', 'exists:clinics,id'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
        ];
    }
}
