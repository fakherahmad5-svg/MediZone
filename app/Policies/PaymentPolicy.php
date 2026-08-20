<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return (
            $user->hasRole('patient')
            || $user->hasRole('doctor')
            || $user->hasRole('receptionist')
            || $user->hasRole('admin')
        ) && (
            $user->hasPermission('invoices.view')
            || $user->hasPermission('payments.manage')
        );
    }

    public function view(User $user, Payment $payment): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Patient: only their own payment
        if ($user->hasRole('patient')) {
            return $user->patient?->id === $payment->patient_id
                && $user->hasPermission('invoices.view');
        }

        // Doctor: only payments related to their appointments
        if ($user->hasRole('doctor', $payment->clinic_id)) {
            return $user->doctor?->id === $payment->doctor_id
                && $user->hasPermission(
                    'invoices.view',
                    $payment->clinic_id
                );
        }

        // Receptionist: clinic-scoped
        if ($user->hasRole('receptionist', $payment->clinic_id)) {
            return $this->isClinicMember($user, $payment->clinic_id)
                && $user->hasPermission(
                    'payments.manage',
                    $payment->clinic_id
                );
        }

        // Clinic admin
        if ($this->isClinicAdmin($user, $payment->clinic_id)) {
            return $user->hasPermission(
                'payments.manage',
                $payment->clinic_id
            );
        }

        return false;
    }

    /**
     * Payment records are created by the booking/payment flow,
     * not directly by users.
     */
    public function create(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasRole('patient')
            && $user->hasPermission('appointments.create');
    }

    /**
     * Payments must not be edited directly.
     * Payment state is controlled by the payment service/webhook.
     */
    public function update(User $user, Payment $payment): bool
    {
        return false;
    }

    public function delete(User $user, Payment $payment): bool
    {
        return false;
    }

    public function restore(User $user, Payment $payment): bool
    {
        return false;
    }

    public function forceDelete(User $user, Payment $payment): bool
    {
        return false;
    }

    // ---------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------

    private function isClinicMember(
        User $user,
        int $clinicId
    ): bool {
        return $user->clinicUsers()
            ->where('clinic_id', $clinicId)
            ->exists();
    }

    private function isClinicAdmin(
        User $user,
        int $clinicId
    ): bool {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasRole('admin', $clinicId)
            && $this->isClinicMember($user, $clinicId);
    }
}