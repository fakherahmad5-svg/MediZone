<?php

namespace App\Modules\Payments\Services;

use App\Core\Exceptions\AuthorizationException;
use App\Core\Exceptions\NotFoundException;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class PaymentQueryService
{
    public function forUser(User $user, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->scopeToUser(Payment::query(), $user)
            ->when(! empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->latest('id')
            ->paginate($perPage);
    }

    public function findForUser(User $user, int $paymentId): Payment
    {
        $payment = $this->scopeToUser(Payment::query(), $user)
            ->where('id', $paymentId)
            ->first();

        if (! $payment) {
            throw new NotFoundException('Payment not found.');
        }

        return $payment;
    }

    // ─────────────────────────────────────────────────────────────
    //  Private Helpers
    // ─────────────────────────────────────────────────────────────

    /**
     * Scopes the query to whatever the authenticated user is allowed
     * to see, based on their role. This is data-scoping only — the
     * finer-grained permission check (e.g. invoices.view vs
     * payments.manage) still happens in PaymentPolicy via
     * $this->authorize() in the controller.
     */
    private function scopeToUser(Builder $query, User $user): Builder
    {
        $query->with(['appointment', 'doctor.user', 'patient.user', 'clinic']);

        if ($user->isSuperAdmin()) {
            return $query;
        }

        if ($user->hasRole('patient')) {
            $patient = $user->patient;

            if (! $patient) {
                throw new NotFoundException('Patient profile not found for this account.');
            }

            return $query->where('patient_id', $patient->id);
        }

        if ($user->hasRole('doctor')) {
            $doctor = $user->doctor;

            if (! $doctor) {
                throw new NotFoundException('Doctor profile not found for this account.');
            }

            return $query->where('doctor_id', $doctor->id);
        }

        if ($user->hasRole('receptionist') || $user->hasRole('admin')) {
            $clinicId = $user->clinicUsers()->value('clinic_id');

            if (! $clinicId) {
                throw new AuthorizationException('Your account is not linked to any clinic.');
            }

            return $query->where('clinic_id', $clinicId);
        }

        throw new AuthorizationException('You are not authorized to view payments.');
    }
}