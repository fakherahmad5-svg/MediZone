<?php

namespace App\Modules\Doctors\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadDoctorCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'certificate' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:8192'], // 8MB
        ];
    }
}
