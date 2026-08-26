<?php

namespace App\Modules\Appointments\Services;

use App\Core\Enums\AccessStatus;
use App\Core\Enums\AppointmentStatus;
use App\Core\Enums\NotificationType;
use App\Core\Exceptions\AuthorizationException;
use App\Core\Exceptions\BusinessException;
use App\Core\Services\BaseService;
use App\Core\Services\NotificationService;
use App\Models\Appointment;
use App\Models\ClinicLog;
use App\Models\PatientDoctorAccess;
use App\Models\User;
use App\Modules\Payments\Services\PaymentService;
use App\Modules\Scheduling\Services\DoctorTimeSlotService;


class AppointmentStatusService extends BaseService
{
    public function __construct(private readonly PaymentService $payments) {}

    public function checkIn(Appointment $appointment, User $actor): Appointment
    {
        $this->ensureActorCan($appointment, $actor, ['receptionist', 'doctor', 'admin']);
        $this->ensureTransition($appointment, AppointmentStatus::Scheduled, AppointmentStatus::CheckedIn);

        return $this->applyTransition($appointment, $actor, AppointmentStatus::CheckedIn, 'appointment_checked_in');
    }


    public function startConsultation(Appointment $appointment, User $actor): Appointment
    {
        $this->ensureActorCan($appointment, $actor, ['doctor']);
        $this->ensureTransition($appointment, AppointmentStatus::CheckedIn, AppointmentStatus::InProgress);

        return $this->applyTransition($appointment, $actor, AppointmentStatus::InProgress, 'appointment_started');
    }


    public function complete(Appointment $appointment, User $actor): Appointment
    {
        $this->ensureActorCan($appointment, $actor, ['doctor']);
        $this->ensureTransition($appointment, AppointmentStatus::InProgress, AppointmentStatus::Completed);

        $result = $this->applyTransition($appointment, $actor, AppointmentStatus::Completed, 'appointment_completed');

        $this->payments->settleOnCompletion($result);

        $result->loadMissing(['doctor.user', 'patient.user']);
        app(NotificationService::class)->notify($result->patient->user, NotificationType::AppointmentCompleted, [
            'doctor_name' => $this->doctorName($result),
            'appointment_id' => $result->id,
        ]);

        return $result; }


    public function cancel(Appointment $appointment, User $actor, string $reason): Appointment
    {
        $actorKind = $this->ensureActorCan($appointment, $actor, ['patient', 'doctor', 'receptionist', 'admin']);

        if ($appointment->status->isTerminal()) {
            throw new BusinessException('This appointment is already in a final state and cannot be cancelled.');
        }

        if ($actorKind === 'patient') {
            $this->ensureCancellableByPatient($appointment);
        }

        return $this->transaction(function () use ($appointment, $actor, $actorKind, $reason) {
            // Re-fetch and lock the row inside the transaction. The
            // isTerminal() check above reads an unlocked row, so two
            // concurrent cancel requests for the same appointment (double
            // tap, retry after a slow response, two devices) could both
            // pass it before either commits. Locking here makes the second
            // request wait for the first to commit, then re-checking
            // isTerminal() on the fresh locked row rejects it instead of
            // running the release/refund logic twice.
            $locked = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);

            if ($locked->status->isTerminal()) {
                throw new BusinessException('This appointment is already in a final state and cannot be cancelled.');
            }

            $locked->update([
                'status'               => AppointmentStatus::Cancelled->value,
                'cancellation_reason' => $reason,
            ]);

            if ($locked->slot_id) {
                app(DoctorTimeSlotService::class)->release($locked->slot_id);
            }
            $this->closeAccess($locked, AccessStatus::Revoked, $actor);
            $this->log($locked, $actor, 'appointment_cancelled', $reason);

            $this->payments->settleOnCancellation($locked, $actorKind);

            $fresh = $locked->fresh();
            $fresh->loadMissing(['doctor.user', 'patient.user', 'slot']);
            app(NotificationService::class)->notify($fresh->patient->user, NotificationType::AppointmentCancelled, [
                'doctor_name'      => $this->doctorName($fresh),
                'appointment_date' => $fresh->slot?->starts_at?->format('Y-m-d H:i'),
                'appointment_id'   => $fresh->id,
            ]);

            return $fresh;
        });
    }


    public function markNoShow(Appointment $appointment, User $actor): Appointment
    {
        $this->ensureActorCan($appointment, $actor, ['receptionist', 'doctor']);
        $this->ensureTransition($appointment, AppointmentStatus::Scheduled, AppointmentStatus::NoShow);

        return $this->transaction(function () use ($appointment, $actor) {
            $appointment->update(['status' => AppointmentStatus::NoShow->value]);

            if ($appointment->slot_id) {
                app(DoctorTimeSlotService::class)->release($appointment->slot_id);
            }
            $this->closeAccess($appointment, AccessStatus::Revoked, $actor);
            $this->log($appointment, $actor, 'appointment_no_show');

            $this->payments->settleOnNoShow($appointment);

            return $appointment->fresh();
        });
    }

    // ─────────────────────────────────────────────────────────────
    //  Private Helpers
    // ─────────────────────────────────────────────────────────────

    private function applyTransition(Appointment $appointment, User $actor, AppointmentStatus $newStatus, string $logAction): Appointment
    {
        return $this->transaction(function () use ($appointment, $actor, $newStatus, $logAction) {
            $appointment->update(['status' => $newStatus->value]);

            $this->log($appointment, $actor, $logAction);

            return $appointment->fresh();
        });
    }

    private function closeAccess(Appointment $appointment, AccessStatus $newStatus, ?User $actor): void
    {
        PatientDoctorAccess::where('appointment_id', $appointment->id)
            ->where('status', AccessStatus::Active->value)
            ->update(array_filter([
                'status'        => $newStatus->value,
                'revoked_at'    => $newStatus === AccessStatus::Revoked ? now() : null,
                'revoked_by'    => $newStatus === AccessStatus::Revoked ? $actor?->id : null,
                'revoke_reason' => $newStatus === AccessStatus::Revoked ? "appointment_{$appointment->status->value}" : null,
            ], fn ($v) => $v !== null));
    }
    private function ensureTransition(Appointment $appointment, AppointmentStatus $requiredCurrent, AppointmentStatus $target): void
    {
        if ($appointment->status !== $requiredCurrent) {
            throw new BusinessException(
                "Cannot move appointment from [{$appointment->status->value}] to [{$target->value}]. " .
                "It must currently be [{$requiredCurrent->value}]."
            );
        }
    }


    private function ensureActorCan(Appointment $appointment, User $actor, array $allowedKinds): string
    {
        if (in_array('patient', $allowedKinds, true) && $actor->patient?->id === $appointment->patient_id) {
            return 'patient';
        }

        if (in_array('doctor', $allowedKinds, true) && $actor->doctor?->id === $appointment->doctor_id) {
            return 'doctor';
        }

        if (in_array('receptionist', $allowedKinds, true)) {
            $clinicId = $actor->clinicUsers()->value('clinic_id');
            if ($clinicId !== null && $clinicId === $appointment->clinic_id) {
                return 'receptionist';
            }
        }

        if (in_array('admin', $allowedKinds, true) && $actor->isSuperAdmin()) {
            return 'admin';
        }

        throw new AuthorizationException('You are not authorized to perform this action on this appointment.');
    }


    private function ensureCancellableByPatient(Appointment $appointment): void
    {
        $minHoursBeforeCancel = 2;

        $startsAt = $appointment->slot?->starts_at;

        if ($startsAt && now()->diffInHours($startsAt, false) < $minHoursBeforeCancel) {
            throw new BusinessException(
                "Appointments can only be cancelled at least {$minHoursBeforeCancel} hours in advance. " .
                'Please contact the clinic directly for last-minute changes.'
            );
        }
    }

    private function log(Appointment $appointment, User $actor, string $action, ?string $description = null): void
    {
        ClinicLog::create([
            'clinic_id'   => $appointment->clinic_id,
            'user_id'     => $actor->id,
            'action'      => $action,
            'description' => $description ?? "Appointment #{$appointment->id} — {$action}",
            'entity_type' => Appointment::class,
            'entity_id'   => $appointment->id,
        ]);
    }
    private function doctorName(Appointment $appointment): string
    {
        $user = $appointment->doctor?->user;

        return $user ? trim("{$user->first_name} {$user->last_name}") : '';
    }
}
