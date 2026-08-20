<?php

namespace App\Modules\Appointments\Services;

use App\Core\Enums\AppointmentStatus;
use App\Core\Enums\PaymentStatus;
use App\Core\Enums\RefundStatus;
use App\Core\Enums\UserRole;
use App\Core\Exceptions\AppointmentAlreadyPastException;
use App\Core\Exceptions\AppointmentMissingSlotException;
use App\Core\Exceptions\AppointmentNotCancellableException;
use App\Core\Exceptions\DuplicateRefundException;
use App\Core\Exceptions\PatientCancellationWindowExpiredException;
use App\Core\Exceptions\StripeRefundFailedException;
use App\Models\Appointment;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Modules\Payments\Services\StripePaymentService;
use App\Modules\Scheduling\Services\DoctorTimeSlotService;
use Illuminate\Support\Facades\DB;

class AppointmentCancellationService
{
    private const HOURS_THRESHOLD = 24;

    private const PATIENT_MIN_CANCEL_HOURS = 2;

    private const NEAR_TERM_ADMIN_FEE_PERCENT = '6.00';

    private const FAR_TERM_ADMIN_FEE_PERCENT = '3.00';

    private const STAFF_INITIATED_FEE_PERCENT = '0.00';

    public function __construct(
        protected StripePaymentService $stripePaymentService
    ) {
    }

    public function cancel(
        Appointment $appointment,
        ?string $reason = null,
        ?User $initiatedBy = null
    ): ?Refund {
        $appointment->loadMissing([
            'slot',
            'latestPayment',
        ]);

        $this->assertCancellable($appointment);

        if (! $appointment->slot) {
            throw AppointmentMissingSlotException::forAppointment($appointment->id);
        }

        $hoursRemaining = $this->hoursUntilAppointment($appointment);

        if ($hoursRemaining < 0) {
            throw AppointmentAlreadyPastException::make();
        }

        // Patients may not cancel within 2 hours of the appointment.
        // Staff (doctor/admin/receptionist) are exempt — they can cancel
        // at any time, per the confirmed 100%-refund-regardless-of-timing rule.
        if (! $this->isStaffInitiated($initiatedBy, $appointment)
            && $hoursRemaining < self::PATIENT_MIN_CANCEL_HOURS) {
            throw PatientCancellationWindowExpiredException::make();
        }

        $payment = $appointment->latestPayment;

        /*
         * If there is no refundable payment, we can still
         * cancel the appointment after the past-time check.
         */
        if (! $payment || ! $payment->isRefundable()) {
            $appointment->update([
                'status' => AppointmentStatus::Cancelled,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            if ($appointment->slot_id) {
                app(DoctorTimeSlotService::class)->release($appointment->slot_id);
            }

            return null;
        }

        $adminFeePercentage = $this->resolveAdminFeePercentage(
            $initiatedBy,
            $appointment,
            $hoursRemaining
        );

        /*
         * Every Payment record has a Stripe PaymentIntent.
         *
         * ONLINE:
         *   payment->amount = full amount paid through Stripe.
         *
         * CASH:
         *   payment->amount = deposit paid through Stripe.
         *   The remaining amount is paid at the clinic and
         *   does not participate in this Stripe refund.
         */
        $paidAmount = (string) $payment->amount;

        // Admin fee is calculated from the FULL appointment price,
        // not from the amount actually paid (e.g. deposit).
        $appointmentAmount = (string) $appointment->price;

        $adminFeeAmount = bcmul(
            $appointmentAmount,
            bcdiv($adminFeePercentage, '100', 4),
            2
        );

        // Refund cannot be negative.
        // For CASH bookings, only the paid deposit can be refunded.
        $refundAmount = bcsub(
            $paidAmount,
            $adminFeeAmount,
            2
        );

        if (bccomp($refundAmount, '0.00', 2) < 0) {
            $refundAmount = '0.00';
        }

        /** Transaction #1:
         * Validate and reserve the refund.
         */
        $refund = DB::transaction(
            function () use (
                $appointment,
                $payment,
                $reason,
                $initiatedBy,
                $paidAmount,
                $adminFeePercentage,
                $adminFeeAmount,
                $refundAmount
            ) {
                $lockedPayment = $payment
                    ->newQuery()
                    ->lockForUpdate()
                    ->findOrFail($payment->id);

                $this->assertNoDuplicateRefund($lockedPayment);

                return Refund::create([
                    'payment_id' => $lockedPayment->id,
                    'appointment_id' => $appointment->id,
                    'initiated_by' => $initiatedBy?->id,
                    'original_amount' => $paidAmount,
                    'admin_fee_percentage' => $adminFeePercentage,
                    'admin_fee_amount' => $adminFeeAmount,
                    'refund_amount' => $refundAmount,
                    'status' => RefundStatus::Pending,
                    'cancellation_reason' => $reason,
                    'cancelled_at' => now(),
                ]);
            }
        );

        /*
         * FIX (Bug B): a $0.00 refund is a valid outcome (the admin fee
         * consumed the entire deposit) — NOT a failure. Stripe's Refund
         * API rejects zero-amount refunds, so we must not call it at all
         * in this case. Instead: record the Refund as Succeeded with no
         * Stripe refund id, leave the Payment untouched (nothing was
         * actually refunded), and still cancel the appointment.
         */
        if (bccomp($refundAmount, '0.00', 2) === 0) {
            DB::transaction(function () use ($appointment, $payment, $refund, $reason) {
                $refund->update([
                    'status' => RefundStatus::Succeeded,
                    'stripe_refund_id' => null,
                    'processed_at' => now(),
                ]);

                $appointment->update([
                    'status' => AppointmentStatus::Cancelled,
                    'cancelled_at' => now(),
                    'cancellation_reason' => $reason,
                    // Mirrors payment.refunded_amount, which is unchanged —
                    // nothing was refunded in this branch.
                    'refund_amount' => (string) ($payment->refunded_amount ?? '0.00'),
                ]);
            });

            if ($appointment->slot_id) {
                app(DoctorTimeSlotService::class)->release($appointment->slot_id);
            }

            return $refund->fresh();
        }

        /*
         * Stripe call must stay OUTSIDE the DB transaction.
         */
        try {
            $stripeRefund = $this->stripePaymentService
                ->refundDestinationCharge(
                    $payment,
                    $refundAmount
                );
        } catch (StripeRefundFailedException $e) {
            /*
             * Stripe failed.
             *
             * Do NOT cancel the appointment.
             * Do NOT modify the Payment.
             * Do NOT release the slot.
             * Keep the Refund as a Failed record.
             */
            $refund->update([
                'status' => RefundStatus::Failed,
                'failure_reason' => $e->getMessage(),
            ]);

            throw $e;
        }

        /*
         * Transaction #2:
         * Record the confirmed Stripe refund and
         * finalize the cancellation.
         */
        DB::transaction(
            function () use (
                $appointment,
                $payment,
                $refund,
                $stripeRefund,
                $refundAmount,
                $reason
            ) {
                $newRefundedTotal = bcadd(
                    (string) ($payment->refunded_amount ?? '0.00'),
                    $refundAmount,
                    2
                );

                $isFullyRefunded = bccomp(
                    $newRefundedTotal,
                    (string) $payment->amount,
                    2
                ) >= 0;

                $payment->update([
                    'refunded_amount' => $newRefundedTotal,
                    'refunded_at' => now(),
                    'status' => $isFullyRefunded
                        ? PaymentStatus::Refunded
                        : PaymentStatus::PartiallyRefunded,
                ]);

                $refund->update([
                    'status' => RefundStatus::Succeeded,
                    'stripe_refund_id' => $stripeRefund->id,
                    'processed_at' => now(),
                ]);

                $appointment->update([
                    'status' => AppointmentStatus::Cancelled,
                    'cancelled_at' => now(),
                    'cancellation_reason' => $reason,
                    'refund_amount' => $newRefundedTotal,
                ]);
            }
        );

        if ($appointment->slot_id) {
            app(DoctorTimeSlotService::class)->release($appointment->slot_id);
        }

        return $refund->fresh();
    }

    protected function resolveAdminFeePercentage(
        ?User $initiatedBy,
        Appointment $appointment,
        float $hoursRemaining
    ): string {
        if ($this->isStaffInitiated($initiatedBy, $appointment)) {
            return self::STAFF_INITIATED_FEE_PERCENT;
        }

        return $hoursRemaining <= self::HOURS_THRESHOLD
            ? self::NEAR_TERM_ADMIN_FEE_PERCENT
            : self::FAR_TERM_ADMIN_FEE_PERCENT;
    }

    protected function isStaffInitiated(
        ?User $initiatedBy,
        Appointment $appointment
    ): bool {
        if (! $initiatedBy) {
            return false;
        }

        /*
         * Global/Super Admin:
         * isSuperAdmin() checks for the global role
         * where clinic_id is NULL.
         */
        if ($initiatedBy->isSuperAdmin()) {
            return true;
        }

        /*
         * Doctor/Admin/Receptionist roles are clinic-scoped.
         * Therefore we must check the role against
         * the appointment's clinic.
         *
         * NEW: Receptionist added — receptionist-initiated
         * cancellations are staff-initiated (100% refund,
         * exempt from the 2-hour patient restriction).
         */
        return $initiatedBy->hasRole(
            UserRole::Doctor->value,
            $appointment->clinic_id
        ) || $initiatedBy->hasRole(
            UserRole::Admin->value,
            $appointment->clinic_id
        ) || $initiatedBy->hasRole(
            UserRole::Receptionist->value,
            $appointment->clinic_id
        );
    }

    protected function assertCancellable(
        Appointment $appointment
    ): void {
        if ($appointment->status->isTerminal()) {
            throw AppointmentNotCancellableException::forStatus(
                $appointment->status->value
            );
        }
    }

    protected function assertNoDuplicateRefund(
        Payment $payment
    ): void {
        $existing = Refund::where(
            'payment_id',
            $payment->id
        )
            ->whereIn('status', [
                RefundStatus::Pending,
                RefundStatus::Succeeded,
            ])
            ->exists();

        if ($existing) {
            throw DuplicateRefundException::forPayment(
                $payment->id
            );
        }
    }

    protected function hoursUntilAppointment(
        Appointment $appointment
    ): float {
        return now()->diffInMinutes(
            $appointment->slot->starts_at,
            false
        ) / 60;
    }
}