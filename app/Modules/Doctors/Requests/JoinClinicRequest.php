<?php

namespace App\Modules\Doctors\Requests;

use App\Models\Clinic;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class JoinClinicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {


        return [
            'clinic_code' => [
                'bail', 'required', 'string', 'size:10',
                function ($attribute, $value, $fail) {
                    if (! Clinic::isValidCodeFormat($value)) {
                        $fail('Invalid clinic code format.');
                    }
                },
                'exists:clinics,code',
            ],
            'consultation_fee' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
        ];
    }


}
