<?php

namespace App\Modules\Appointments\Requests;

use App\Core\Enums\ConsultationType;
use App\Core\Enums\PaymentMethod;
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
            // NEW: patient declares payment method at booking time. This
            // drives whether checkout later charges the full price
            // (ONLINE/Card) or only the deposit (Cash) — see
            // StripePaymentService::resolveCheckoutAmount().
            'payment_method' => ['required', 'string', Rule::in(PaymentMethod::values())],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ];
    }
}