<?php

namespace App\Modules\Appointments\Services;

use App\Core\Enums\AccessStatus;
use App\Core\Enums\AccessType;
use App\Core\Enums\AppointmentStatus;
use App\Core\Enums\ConsultationType;
use App\Core\Exceptions\AuthorizationException;
use App\Core\Exceptions\BusinessException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Services\BaseService;
use App\Core\Services\NotificationService;
use App\Models\Appointment;
use App\Models\ClinicLog;
use App\Models\Doctor;
use App\Models\DoctorClinic;
use App\Models\Patient;
use App\Models\PatientDoctorAccess;
use App\Models\User;
use App\Modules\Scheduling\Services\DoctorTimeSlotService;

class AppointmentBookingService extends BaseService
{

    public function book(User $patientUser, int $slotId, ConsultationType $type, ?string $notes = null): Appointment
    {
        $patient = $this->patientFor($patientUser);

        return $this->transaction(function () use ($patient, $patientUser, $slotId, $type, $notes) {
            $slot = app(DoctorTimeSlotService::class)->lockForBooking($slotId);

            $appointment = Appointment::create([
                'clinic_id'      => $slot->clinic_id,
                'patient_id'     => $patient->id,
                'doctor_id'      => $slot->doctor_id,
                'slot_id'        => $slot->id,
                'status'         => AppointmentStatus::Scheduled->value,
                'encounter_type' => $type->value,
                'price'          => $this->resolvePrice($slot->doctor_id,$slot->clinic_id),
                'created_by'     => $patientUser->id,
                'notes'          => $notes,
            ]);

            $this->log($appointment, $patientUser, 'appointment_booked');
            $this->grantInitialAccess($appointment);
            $this->notifyBooked($appointment);
            return $appointment->fresh(['clinic', 'doctor.user', 'slot']);
        });
    }


    public function bookOnBehalf(User $receptionistUser, int $patientId, int $slotId, ConsultationType $type, ?string $notes = null): Appointment
    {
        $patient = Patient::find($patientId);

        if (! $patient) {
            throw new NotFoundException('Patient not found.');
        }

        return $this->transaction(function () use ($patient, $receptionistUser, $slotId, $type, $notes) {
            $slot = app(DoctorTimeSlotService::class)->lockForBooking($slotId);

            $appointment = Appointment::create([
                'clinic_id'      => $slot->clinic_id,
                'patient_id'     => $patient->id,
                'doctor_id'      => $slot->doctor_id,
                'slot_id'        => $slot->id,
                'status'         => AppointmentStatus::Scheduled->value,
                'encounter_type' => $type->value,
                'price'          => $this->resolvePrice($slot->doctor_id,$slot->clinic_id),
                'created_by'     => $receptionistUser->id,
                'notes'          => $notes,
            ]);

            $this->log($appointment, $receptionistUser, 'appointment_booked_on_behalf');
            $this->grantInitialAccess($appointment);
            $this->notifyBooked($appointment);

            return $appointment->fresh(['clinic', 'doctor.user', 'patient.user', 'slot']);
        });
    }


    public function createWalkIn(User $receptionistUser, int $clinicId, int $patientId, int $doctorId, ConsultationType $type, ?string $notes = null): Appointment
    {
        $patient = Patient::find($patientId);

        if (! $patient) {
            throw new NotFoundException('Patient not found.');
        }

        $doctor = Doctor::find($doctorId);

        if (! $doctor) {
            throw new NotFoundException('Doctor not found.');
        }

        if (! $doctor->isVerified()) {
            throw new BusinessException('This doctor is not currently verified.');
        }

        return $this->transaction(function () use ($clinicId, $patient, $doctor, $receptionistUser, $type, $notes) {
            $appointment = Appointment::create([
                'clinic_id'      => $clinicId,
                'patient_id'     => $patient->id,
                'doctor_id'      => $doctor->id,
                'slot_id'        => null,
                'status'         => AppointmentStatus::CheckedIn->value,
                'encounter_type' => $type->value,
                'price'          => $this->resolvePrice($doctor->id,$clinicId),
                'created_by'     => $receptionistUser->id,
                'notes'          => $notes,
            ]);

            $this->log($appointment, $receptionistUser, 'walk_in_appointment_created');
            $this->grantInitialAccess($appointment);
            $this->notifyBooked($appointment);
            return $appointment->fresh(['clinic', 'doctor.user', 'patient.user']);
        });
    }


    public function reschedule(User $patientUser, Appointment $appointment, int $newSlotId): Appointment
    {
        $patient = $this->patientFor($patientUser);

        if ($appointment->patient_id !== $patient->id) {
            throw new AuthorizationException('This appointment does not belong to you.');
        }

        if (! $appointment->isScheduled()) {
            throw new BusinessException('Only scheduled appointments can be rescheduled.');
        }

        return $this->transaction(function () use ($appointment, $newSlotId) {
            $newSlot = app(DoctorTimeSlotService::class)->lockForBooking($newSlotId);

            if ($newSlot->doctor_id !== $appointment->doctor_id) {

                app(DoctorTimeSlotService::class)->release($newSlot->id);

                throw new BusinessException('The new time slot must belong to the same doctor.');
            }

            $oldSlotId = $appointment->slot_id;

            $appointment->update([
                'slot_id'   => $newSlot->id,
                'clinic_id' => $newSlot->clinic_id,
            ]);

            if ($oldSlotId) {
                app(DoctorTimeSlotService::class)->release($oldSlotId);
            }
            $fresh = $appointment->fresh(['clinic', 'doctor.user', 'slot']);

            app(NotificationService::class)->notify($fresh->patient->user, NotificationType::AppointmentRescheduled, [
                'doctor_name' => $this->doctorName($fresh),
                'new_date'    => $fresh->slot?->starts_at?->format('Y-m-d H:i'),
            ]);

            return $fresh;
        });
    }

    // ─────────────────────────────────────────────────────────────


    private function grantInitialAccess(Appointment $appointment): void
    {
        PatientDoctorAccess::create([
            'patient_id'     => $appointment->patient_id,
            'doctor_id'      => $appointment->doctor_id,
            'appointment_id' => $appointment->id,
            'access_type'    => $appointment->status === AppointmentStatus::CheckedIn
                ? AccessType::Full->value
                : AccessType::ReadOnly->value,
            'status'      => AccessStatus::Active->value,
            'granted_at'  => now(),
        ]);
    }
    private function notifyBooked(Appointment $appointment): void
    {
        $appointment->loadMissing(['doctor.user', 'patient.user', 'slot']);

        app(NotificationService::class)->notify($appointment->patient->user, NotificationType::AppointmentConfirmed, [
            'doctor_name'      => $this->doctorName($appointment),
            'appointment_date' => $appointment->slot?->starts_at?->format('Y-m-d H:i')
                ?? now()->format('Y-m-d H:i'), // Walk-in: بلا slot، الآن هو موعد الزيارة فعلياً.
        ]);
    }

    private function doctorName(Appointment $appointment): string
    {
        $user = $appointment->doctor?->user;

        return $user ? trim("{$user->first_name} {$user->last_name}") : '';
    }

    private function patientFor(User $user): Patient
    {
        $patient = $user->patient;

        if (! $patient) {
            throw new NotFoundException('Patient profile not found for this account.');
        }

        return $patient;
    }


    private function resolvePrice(int $doctorId, int $clinicId): float
    {
        $consultationFee = DoctorClinic::query()
            ->where('doctor_id', $doctorId)
            ->where('clinic_id', $clinicId)
            ->value('consultation_fee');

        return (float) ($consultationFee ?? 0);
    }

    private function log(Appointment $appointment, User $actor, string $action): void
    {
        ClinicLog::create([
            'clinic_id'   => $appointment->clinic_id,
            'user_id'     => $actor->id,
            'action'      => $action,
            'description' => "Appointment #{$appointment->id} — {$action}",
            'entity_type' => Appointment::class,
            'entity_id'   => $appointment->id,
        ]);
    }
}
