<?php

namespace Database\Factories;

use App\Core\Enums\PaymentMethod;
use App\Core\Enums\PaymentStatus;
use App\Models\Appointment;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'appointment_id' => Appointment::factory(),

            // Derived from the given appointment_id so a Payment never
            // ends up pointing at a DIFFERENT patient/doctor/clinic than
            // its own appointment — falls back to a fresh factory only if
            // somehow no appointment_id was resolved yet.
            'patient_id' => fn (array $attrs) => Appointment::find($attrs['appointment_id'])?->patient_id
                ?? \App\Models\Patient::factory()->create()->id,
            'doctor_id' => fn (array $attrs) => Appointment::find($attrs['appointment_id'])?->doctor_id
                ?? \App\Models\Doctor::factory()->create()->id,
            'clinic_id' => fn (array $attrs) => Appointment::find($attrs['appointment_id'])?->clinic_id
                ?? \App\Models\Clinic::factory()->create()->id,

            'invoice_id' => null,
            'amount' => '100.00',
            'currency' => 'usd',
            'method' => PaymentMethod::Card,
            'status' => PaymentStatus::Pending,
            'stripe_checkout_session_id' => null,
            'stripe_payment_intent_id' => null,
            'stripe_charge_id' => null,
            'stripe_transfer_id' => null,
            'refunded_amount' => null,
            'paid_at' => null,
            'refunded_at' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);
    }
}