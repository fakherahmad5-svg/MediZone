<?php

namespace Database\Factories;

use App\Core\Enums\AppointmentStatus;
use App\Core\Enums\ConsultationType;
use App\Core\Enums\PaymentMethod;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorTimeSlot;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    public function definition(): array
    {
        return [
            'clinic_id' => Clinic::factory(),
            'patient_id' => Patient::factory(),
            'doctor_id' => Doctor::factory(),

            // Default slot: more than 24 hours out.
            // The slot is tied to the same clinic and doctor.
            'slot_id' => fn (array $attributes) => DoctorTimeSlot::factory()->create([
                'clinic_id' => $attributes['clinic_id'],
                'doctor_id' => $attributes['doctor_id'],
                'starts_at' => now()->addDays(3),
                'ends_at' => now()->addDays(3)->addHour(),
            ])->id,

            'status' => AppointmentStatus::Scheduled,
            'encounter_type' => ConsultationType::InPerson,
            'payment_method' => PaymentMethod::Card,

            'price' => '100.00',

            // Phase 7 commission snapshot
            'commission_percentage' => '10.00',
            'commission_amount' => '10.00',

            'deposit_amount' => null,
            'remaining_cash_amount' => null,

            'cancellation_reason' => null,
            'cancelled_at' => null,
            'refund_amount' => null,
            'stripe_payment_intent_id' => null,

            'created_by' => User::factory(),
            'notes' => null,
        ];
    }

    /**
     * Override the default slot with one starting at a specific time.
     *
     * Useful for tests that need to deterministically hit
     * the <=24h or >24h cancellation/admin-fee tier.
     */
    public function slotStartingAt(\DateTimeInterface $startsAt): static
    {
        return $this->state(fn (array $attributes) => [
            'slot_id' => fn (array $attrs) => DoctorTimeSlot::factory()->create([
                'clinic_id' => $attrs['clinic_id'],
                'doctor_id' => $attrs['doctor_id'],
                'starts_at' => $startsAt,
                'ends_at' => \Carbon\Carbon::parse($startsAt)->addHour(),
            ])->id,
        ]);
    }

    public function cash(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => PaymentMethod::Cash,
            'deposit_amount' => '20.00',
            'remaining_cash_amount' => '80.00',
        ]);
    }
}