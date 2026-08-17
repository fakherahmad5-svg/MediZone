<?php

namespace App\Modules\Doctors\Requests;

use Illuminate\Foundation\Http\FormRequest;


class CreateClinicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'clinic_name'         => ['required', 'string', 'max:150'],
            'clinic_address'      => ['required', 'string', 'max:255'],
            'clinic_phone'        => ['sometimes', 'string', 'max:20'],
            'clinic_license_file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'latitude'             => ['required', 'numeric'],
            'longitude'             => ['required', 'numeric'],
            'consultation_fee'     => ['required', 'numeric', 'min:0', 'max:99999999.99'],
        ];
    }
}
