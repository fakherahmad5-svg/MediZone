<?php

namespace App\Modules\Appointments\Services;

use App\Core\Enums\AppointmentStatus;
use App\Core\Enums\ConsultationType;
use App\Core\Enums\PaymentMethod;
use App\Core\Enums\PaymentStatus;
use App\Core\Exceptions\AuthorizationException;
use App\Core\Exceptions\BusinessException;
use App\Core\Exceptions\DomainValidationException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Services\BaseService;
use App\Models\Appointment;
use App\Models\ClinicLog;
use App\Models\Payment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use App\Modules\Scheduling\Services\DoctorTimeSlotService;

class AppointmentBookingService extends BaseService
{
    public function book(
        User $patientUser,
        int $slotId,
        ConsultationType $type,
        PaymentMethod $paymentMethod,
        ?string $notes = null
    ): Appointment {
        $patient = $this->patientFor($patientUser);

        return $this->transaction(function () use (
            $patient,
            $patientUser,
            $slotId,
            $type,
            $paymentMethod,
            $notes
        ) {
        
$slot = app(DoctorTimeSlotService::class)->findAvailableOrFail($slotId);

            $doctor = Doctor::with(['profile', 'settings'])->find($slot->doctor_id);

            $price = number_format(
                (float) ($doctor?->profile?->consultation_fee ?? 0),
                2,
                '.',
                ''
            );

            $snapshot = $this->computeFinancialSnapshot(
                $doctor,
                $price,
                $paymentMethod
            );

            $appointment = Appointment::create([
                'clinic_id' => $slot->clinic_id,
                'patient_id' => $patient->id,
                'doctor_id' => $slot->doctor_id,
                'slot_id' => $slot->id,

                // Appointment is NOT confirmed until the webhook says so.
                'status' => AppointmentStatus::AwaitingPayment->value,
                'encounter_type' => $type->value,
                'payment_method' => $paymentMethod->value,
                'price' => $price,
                'created_by' => $patientUser->id,
                'notes' => $notes,

                // Financial snapshot — frozen at booking time, never
                // recomputed from live DoctorSetting/commission_percentage
                // afterward, even if the admin changes those later.
                'deposit_type' => $snapshot['deposit_type'],
                'deposit_percentage' => $snapshot['deposit_percentage'],
                'deposit_amount' => $snapshot['deposit_amount'],
                'remaining_cash_amount' => $snapshot['remaining_cash_amount'],
                'commission_percentage' => $snapshot['commission_percentage'],
                'commission_amount' => $snapshot['commission_amount'],
            ]);

            $this->log(
                $appointment,
                $patientUser,
                'appointment_booked'
            );

            return $appointment->fresh([
                'clinic',
                'doctor.user',
                'slot',
            ]);
        });
    }

    public function bookOnBehalf(
    User $receptionistUser,
    int $patientId,
    int $slotId,
    ConsultationType $type,
    ?string $notes = null
): Appointment {
    $patient = Patient::find($patientId);

    if (! $patient) {
        throw new NotFoundException('Patient not found.');
    }

    return $this->transaction(function () use (
        $patient,
        $receptionistUser,
        $slotId,
        $type,
        $notes
    ) {
        $slot = app(DoctorTimeSlotService::class)
            ->findAvailableOrFail($slotId);

        $doctor = Doctor::find($slot->doctor_id);
        $price = number_format($this->resolvePrice($slot->doctor_id), 2, '.', '');
        $commission = $this->computeCommissionSnapshot($doctor, $price);

        $appointment = Appointment::create([
            'clinic_id' => $slot->clinic_id,
            'patient_id' => $patient->id,
            'doctor_id' => $slot->doctor_id,
            'slot_id' => $slot->id,

            
            'status' => AppointmentStatus::AwaitingPayment->value,
            'encounter_type' => $type->value,
            'payment_method' => PaymentMethod::Cash->value,
            'price' => $price,
            'deposit_type' => null,
            'deposit_amount' => '0.00',
            'remaining_cash_amount' => $price,

            'commission_percentage' => $commission['commission_percentage'],
            'commission_amount' => $commission['commission_amount'],
            'created_by' => $receptionistUser->id,
            'notes' => $notes,
        ]);

        Payment::create([
            'appointment_id' => $appointment->id,
            'patient_id' => $patient->id,
            'doctor_id' => $slot->doctor_id,
            'clinic_id' => $slot->clinic_id,
            'amount' => $price,
            'currency' => 'usd',
            'method' => PaymentMethod::Cash->value,
            'status' => PaymentStatus::Pending->value,
        ]);

        $this->log(
            $appointment,
            $receptionistUser,
            'appointment_booked_on_behalf'
        );

        return $appointment->fresh([
            'clinic',
            'doctor.user',
            'patient.user',
            'slot',
        ]);
    });
}
/**
 * Receptionist confirms she physically received the cash payment
 * for a bookOnBehalf() appointment. This is the ONLY point where
 * the slot actually gets reserved for this flow.
 */
public function confirmCashPayment(
    Appointment $appointment,
    User $receptionistUser
): Appointment {
    return $this->transaction(function () use (
        $appointment,
        $receptionistUser
    ) {
        $locked = Appointment::query()
            ->whereKey($appointment->id)
            ->lockForUpdate()
            ->firstOrFail();

        if ($locked->status !== AppointmentStatus::AwaitingPayment) {
            throw new BusinessException(
                'This appointment is not awaiting cash payment confirmation.'
            );
        }
  if ($locked->slot_id) {
            app(DoctorTimeSlotService::class)
                ->lockForBooking($locked->slot_id);
        }
$payment = Payment::where('appointment_id', $locked->id)
    ->where('status', PaymentStatus::Pending)
    ->first();

if (! $payment) {
    throw new BusinessException(
        'No pending cash payment exists for this appointment.'
    );
}

if ($locked->slot_id) {
    app(DoctorTimeSlotService::class)
        ->lockForBooking($locked->slot_id);
}

$payment->update([
    'status' => PaymentStatus::Paid,
    'paid_at' => now(),
]);

$locked->update([
    'status' => AppointmentStatus::Scheduled,
]);
        $this->log(
            $locked,
            $receptionistUser,
            'appointment_cash_payment_confirmed'
        );

        return $locked->fresh([
            'clinic',
            'doctor.user',
            'patient.user',
            'slot',
        ]);
    });
}
public function createWalkIn(
    User $receptionistUser,
    int $clinicId,
    int $patientId,
    int $doctorId,
    ConsultationType $type,
    ?string $notes = null
): Appointment {
    $patient = Patient::find($patientId);

    if (! $patient) {
        throw new NotFoundException('Patient not found.');
    }

    $doctor = Doctor::find($doctorId);

    if (! $doctor) {
        throw new NotFoundException('Doctor not found.');
    }

    if (! $doctor->isVerified()) {
        throw new BusinessException(
            'This doctor is not currently verified.'
        );
    }

    return $this->transaction(function () use (
        $clinicId,
        $patient,
        $doctor,
        $receptionistUser,
        $type,
        $notes
    ) {
        $price = number_format(
            $this->resolvePrice($doctor->id),
            2,
            '.',
            ''
        );

        $appointment = Appointment::create([
            'clinic_id' => $clinicId,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'slot_id' => null,
            'status' => AppointmentStatus::CheckedIn->value,
            'encounter_type' => $type->value,
            'payment_method' => PaymentMethod::Cash->value,
            'price' => $price,
            'deposit_amount' => '0.00',
            'remaining_cash_amount' => $price,
            'created_by' => $receptionistUser->id,
            'notes' => $notes,
        ]);

        $this->log(
            $appointment,
            $receptionistUser,
            'walk_in_appointment_created'
        );

        return $appointment->fresh([
            'clinic',
            'doctor.user',
            'patient.user',
        ]);
    });
}

    public function reschedule(
        User $patientUser,
        Appointment $appointment,
        int $newSlotId
    ): Appointment {
        $patient = $this->patientFor($patientUser);

        if ($appointment->patient_id !== $patient->id) {
            throw new AuthorizationException(
                'This appointment does not belong to you.'
            );
        }

        if (! $appointment->isScheduled()) {
            throw new BusinessException(
                'Only scheduled appointments can be rescheduled.'
            );
        }

        return $this->transaction(function () use (
            $appointment,
            $newSlotId
        ) {
            $newSlot = app(DoctorTimeSlotService::class)
                ->lockForBooking($newSlotId);

            if ($newSlot->doctor_id !== $appointment->doctor_id) {
                app(DoctorTimeSlotService::class)
                    ->release($newSlot->id);

                throw new BusinessException(
                    'The new time slot must belong to the same doctor.'
                );
            }

            $oldSlotId = $appointment->slot_id;

            $appointment->update([
                'slot_id' => $newSlot->id,
                'clinic_id' => $newSlot->clinic_id,
            ]);

            if ($oldSlotId) {
                app(DoctorTimeSlotService::class)
                    ->release($oldSlotId);
            }

            return $appointment->fresh([
                'clinic',
                'doctor.user',
                'slot',
            ]);
        });
    }
     /**
     * Safety-net sweep for appointments stuck in AwaitingPayment past
     * their checkout window. Stripe's own checkout.session.expired
     * webhook is the primary mechanism; this catches cases where that
     * webhook was delayed, missed, or never fired (e.g. the patient
     * never even reached Stripe Checkout).
     */
    
    private function computeCommissionSnapshot(?Doctor $doctor, string $price): array
{
    $commissionPercentage = (string) ($doctor?->commission_percentage ?? '0.00');

    $commissionAmount = bcmul(
        $price,
        bcdiv($commissionPercentage, '100', 4),
        2
    );

    return [
        'commission_percentage' => $commissionPercentage,
        'commission_amount' => $commissionAmount,
    ];
}

    private function patientFor(User $user): Patient
    {
        $patient = $user->patient;

        if (! $patient) {
            throw new NotFoundException(
                'Patient profile not found for this account.'
            );
        }

        return $patient;
    }

    private function resolvePrice(int $doctorId): float
    {
        $doctor = Doctor::with('profile')->find($doctorId);

        return (float) ($doctor?->profile?->consultation_fee ?? 0);
    }

    private function computeFinancialSnapshot(
        ?Doctor $doctor,
        string $price,
        PaymentMethod $paymentMethod
    ): array {
        $commissionPercentage = (string) (
            $doctor?->commission_percentage ?? '0.00'
        );

        $commissionAmount = bcmul(
            $price,
            bcdiv($commissionPercentage, '100', 4),
            2
        );

        if ($paymentMethod !== PaymentMethod::Cash) {
            // ONLINE/Card: the full price is collected through Stripe.
            // No separate "remaining at clinic" amount.
            return [
                'deposit_type' => null,
                'deposit_percentage' => null,
                'deposit_amount' => $price,
                'remaining_cash_amount' => '0.00',
                'commission_percentage' => $commissionPercentage,
                'commission_amount' => $commissionAmount,
            ];
        }

        $settings = $doctor?->settings;

        if (! $settings) {
            throw new DomainValidationException(
                "Doctor #{$doctor?->id} has not configured a cash deposit policy."
            );
        }

        $depositAmount = $settings->calculateDeposit($price);
// Cash deposit must cover the platform commission.
        if (bccomp($depositAmount, $commissionAmount, 2) < 0) {
            throw new DomainValidationException(
                "The cash deposit ({$depositAmount}) must be at least the platform commission ({$commissionAmount})."
            );
        }

        $remaining = bcsub($price, $depositAmount, 2);

        return [
            'deposit_type' => $settings->cash_deposit_type->value,
            'deposit_percentage' => $settings->isPercentageDeposit()
                ? (string) $settings->cash_deposit_value
                : null,
            'deposit_amount' => $depositAmount,
            'remaining_cash_amount' => $remaining,
            'commission_percentage' => $commissionPercentage,
            'commission_amount' => $commissionAmount,
        ];
    }

    private function log(
        Appointment $appointment,
        User $actor,
        string $action
    ): void {
        ClinicLog::create([
            'clinic_id' => $appointment->clinic_id,
            'user_id' => $actor->id,
            'action' => $action,
            'description' => "Appointment #{$appointment->id} — {$action}",
            'entity_type' => Appointment::class,
            'entity_id' => $appointment->id,
        ]);
    }
}