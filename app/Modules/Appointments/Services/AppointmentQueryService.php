<?php

namespace App\Modules\Appointments\Services;

use App\Core\Exceptions\AuthorizationException;
use App\Core\Exceptions\NotFoundException;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;


class AppointmentQueryService
{

    public function forPatient(User $patientUser, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $patient = $this->patientFor($patientUser);

        return Appointment::where('patient_id', $patient->id)
            ->with(['clinic:id,name', 'doctor.user:id,first_name,last_name', 'slot', 'payment'])
            ->when(! empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->latest('id')
            ->paginate($perPage);
    }

    public function findForPatient(User $patientUser, int $appointmentId): Appointment
    {
        $patient = $this->patientFor($patientUser);

        $appointment = Appointment::where('id', $appointmentId)
            ->where('patient_id', $patient->id)
            ->with(['clinic:id,name', 'doctor.user:id,first_name,last_name', 'slot', 'payment'])
            ->first();

        if (! $appointment) {
            throw new NotFoundException('Appointment not found.');
        }

        return $appointment;
    }


    public function forDoctor(User $doctorUser, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $doctor = $this->doctorFor($doctorUser);

        return Appointment::where('doctor_id', $doctor->id)
            ->with(['clinic:id,name', 'patient.user:id,first_name,last_name', 'slot', 'payment'])
            ->when(! empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->when(
                ! empty($filters['date']),
                fn ($q) => $q->whereHas('slot', fn ($sq) => $sq->whereDate('starts_at', $filters['date']))
            )
            ->latest('id')
            ->paginate($perPage);
    }

    public function findForDoctor(User $doctorUser, int $appointmentId): Appointment
    {
        $doctor = $this->doctorFor($doctorUser);

        $appointment = Appointment::where('id', $appointmentId)
            ->where('doctor_id', $doctor->id)
            ->with(['clinic:id,name', 'patient.user:id,first_name,last_name', 'slot', 'payment'])
            ->first();

        if (! $appointment) {
            throw new NotFoundException('Appointment not found.');
        }

        return $appointment;
    }


    public function forReceptionist(User $receptionistUser, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $clinicId = $receptionistUser->clinicUsers()->value('clinic_id');

        if (! $clinicId) {
            throw new AuthorizationException('Your account is not linked to any clinic.');
        }

        return Appointment::where('clinic_id', $clinicId)
            ->with(['doctor.user:id,first_name,last_name', 'patient.user:id,first_name,last_name', 'slot'])
            ->when(! empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->when(! empty($filters['doctor_id']), fn ($q) => $q->where('doctor_id', $filters['doctor_id']))
            ->when(
                ! empty($filters['date']),
                fn ($q) => $q->whereHas('slot', fn ($sq) => $sq->whereDate('starts_at', $filters['date']))
            )
            ->latest('id')
            ->paginate($perPage);
    }

    public function findForReceptionist(User $receptionistUser, int $appointmentId): Appointment
    {
        $clinicId = $receptionistUser->clinicUsers()->value('clinic_id');

        $appointment = Appointment::where('id', $appointmentId)
            ->where('clinic_id', $clinicId)
            ->with(['doctor.user:id,first_name,last_name', 'patient.user:id,first_name,last_name', 'slot'])
            ->first();

        if (! $appointment) {
            throw new NotFoundException('Appointment not found.');
        }

        return $appointment;
    }

    // ─────────────────────────────────────────────────────────────

    private function patientFor(User $user): Patient
    {
        $patient = $user->patient;

        if (! $patient) {
            throw new NotFoundException('Patient profile not found for this account.');
        }

        return $patient;
    }

    private function doctorFor(User $user): Doctor
    {
        $doctor = $user->doctor;

        if (! $doctor) {
            throw new NotFoundException('Doctor profile not found for this account.');
        }

        return $doctor;
    }
}
