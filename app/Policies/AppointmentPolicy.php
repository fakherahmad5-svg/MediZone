<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;

class AppointmentPolicy
{
    public function view(User $user, Appointment $appointment): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->hasRole('patient')) {
            return $this->isPatientOwner($user, $appointment)
                && $user->hasPermission('appointments.view');
        }

        if ($user->hasRole('doctor', $appointment->clinic_id)) {
            return $this->isDoctorOwner($user, $appointment)
                && $user->hasPermission(
                    'appointments.view',
                    $appointment->clinic_id
                );
        }

        if ($user->hasRole('receptionist', $appointment->clinic_id)) {
            return $this->isClinicMember($user, $appointment->clinic_id)
                && $user->hasPermission(
                    'appointments.view',
                    $appointment->clinic_id
                );
        }

        if ($this->isClinicAdmin($user, $appointment->clinic_id)) {
            return $user->hasPermission(
                'appointments.view',
                $appointment->clinic_id
            );
        }

        return false;
    }

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
        ) && $user->hasPermission('appointments.view');
    }

    public function create(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->hasRole('patient')) {
            return $user->hasPermission('appointments.create');
        }

        if ($user->hasRole('receptionist')) {
            return $user->hasPermission('appointments.create');
        }

        if ($user->hasRole('admin')) {
            return $user->hasPermission('appointments.create');
        }

        return false;
    }

    public function cancel(User $user, Appointment $appointment): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->hasRole('patient')) {
            return $this->isPatientOwner($user, $appointment)
                && $user->hasPermission('appointments.cancel');
        }

        if ($user->hasRole('doctor', $appointment->clinic_id)) {
            return $this->isDoctorOwner($user, $appointment)
                && $user->hasPermission(
                    'appointments.cancel',
                    $appointment->clinic_id
                );
        }

        if ($user->hasRole('receptionist', $appointment->clinic_id)) {
            return $this->isClinicMember($user, $appointment->clinic_id)
                && $user->hasPermission(
                    'appointments.cancel',
                    $appointment->clinic_id
                );
        }

        if ($this->isClinicAdmin($user, $appointment->clinic_id)) {
            return $user->hasPermission(
                'appointments.cancel',
                $appointment->clinic_id
            );
        }

        return false;
    }

    public function reschedule(User $user, Appointment $appointment): bool
    {
        if (! $user->hasRole('patient')) {
            return false;
        }

        return $this->isPatientOwner($user, $appointment)
            && $user->hasPermission('appointments.cancel');
    }

    public function checkIn(User $user, Appointment $appointment): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if (
            $user->hasRole('doctor', $appointment->clinic_id)
            && $this->isDoctorOwner($user, $appointment)
        ) {
            return true;
        }
 if (
            $user->hasRole('receptionist', $appointment->clinic_id)
            && $this->isClinicMember($user, $appointment->clinic_id)
        ) {
            return true;
        }

        return $this->isClinicAdmin(
            $user,
            $appointment->clinic_id
        );
    }

    public function start(User $user, Appointment $appointment): bool
    {
        return $user->hasRole('doctor', $appointment->clinic_id)
            && $this->isDoctorOwner($user, $appointment);
    }

    public function complete(User $user, Appointment $appointment): bool
    {
        return $user->hasRole('doctor', $appointment->clinic_id)
            && $this->isDoctorOwner($user, $appointment);
    }

    public function noShow(User $user, Appointment $appointment): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if (
            $user->hasRole('doctor', $appointment->clinic_id)
            && $this->isDoctorOwner($user, $appointment)
        ) {
            return true;
        }

        if (
            $user->hasRole('receptionist', $appointment->clinic_id)
            && $this->isClinicMember($user, $appointment->clinic_id)
        ) {
            return true;
        }

        return $this->isClinicAdmin(
            $user,
            $appointment->clinic_id
        );
    }

    public function update(User $user, Appointment $appointment): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $this->isClinicAdmin(
            $user,
            $appointment->clinic_id
        ) && $user->hasPermission(
            'appointments.manage',
            $appointment->clinic_id
        );
    }

    public function delete(User $user, Appointment $appointment): bool
    {
        return false;
    }

    // ---------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------

    private function isPatientOwner(
        User $user,
        Appointment $appointment
    ): bool {
        return $user->patient?->id === $appointment->patient_id;
    }

    private function isDoctorOwner(
        User $user,
        Appointment $appointment
    ): bool {
        return $user->doctor?->id === $appointment->doctor_id;
    }

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