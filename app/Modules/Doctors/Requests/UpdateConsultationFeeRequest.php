<?php

namespace App\Modules\Doctors\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateConsultationFeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'consultation_fee' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
        ];
    }
}
