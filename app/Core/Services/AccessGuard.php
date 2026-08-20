<?php

namespace App\Core\Services;

use App\Core\Enums\AccessAction;
use App\Core\Enums\AccessStatus;
use App\Core\Enums\AccessType;
use App\Core\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\MedicalRecordAccessLog;
use App\Models\Patient;
use App\Models\PatientDoctorAccess;
use App\Models\User;


class AccessGuard extends BaseService
{

    private const READ_ONLY_WINDOW_HOURS = 48;


    public function levelFor(Doctor $doctor, Patient $patient,Appointment $appointment): ?AccessType
    {
        $access = $this->resolveActiveAccess($doctor, $patient, $appointment);
        if (! $access) {
            return null;
        }

        if (in_array($appointment->status, [AppointmentStatus::CheckedIn, AppointmentStatus::InProgress], true)) {
            return AccessType::Full;
        }

        if ($appointment->status === AppointmentStatus::Scheduled) {
            $windowStart = $appointment->slot?->starts_at?->copy()->subHours(self::READ_ONLY_WINDOW_HOURS);

            if ($windowStart && now()->greaterThanOrEqualTo($windowStart)) {
                return AccessType::ReadOnly;
            }
        }

        return null;
    }


    public function canPerform(Doctor $doctor, Patient $patient, AccessAction $action ,Appointment $appointment): bool
    {
        $level = $this->levelFor($doctor, $patient,$appointment);

        if ($level === null) {
            return false;
        }

        return $action->requiresFullAccess() ? $level === AccessType::Full : true;
    }


    public function resolveActiveAccess(Doctor $doctor, Patient $patient,Appointment $appointment): ?PatientDoctorAccess
    {
        return PatientDoctorAccess::where('doctor_id', $doctor->id)
            ->where('patient_id', $patient->id)
            ->where('status', AccessStatus::Active->value)
            ->whereHas('appointment', function ($q) use ($appointment) {
                $q->where('id',$appointment->id)->whereNotIn('status', [
                    AppointmentStatus::Cancelled->value,
                    AppointmentStatus::NoShow->value,
                    AppointmentStatus::Completed->value,
                ]);
            })
            ->with('appointment.slot')
            ->latest('id')
            ->first();
    }

    public function logAccess(PatientDoctorAccess $access, AccessAction $action, ?string $entityType = null, ?int $entityId = null): void
    {
        MedicalRecordAccessLog::create([
            'access_id'   => $access->id,
            'action'      => $action->value,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'ip_address'  => request()->ip(),
            'accessed_at' => now(),
        ]);
    }
}
