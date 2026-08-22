<?php

namespace App\Modules\Payments\Requests;

use App\Core\Enums\AppointmentPaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PayAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'method' => ['required', 'string', Rule::in(AppointmentPaymentMethod::values())],
        ];
    }
}
