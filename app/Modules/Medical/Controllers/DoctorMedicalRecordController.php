<?php

namespace App\Modules\Medical\Controllers;

use App\Core\Enums\AccessAction;
use App\Core\Exceptions\AuthorizationException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Http\Controllers\BaseController;
use App\Core\Services\AccessGuard;
use App\Models\Appointment;
use App\Models\PatientDoctorAccess;
use App\Models\PatientRecord;
use App\Modules\Medical\Resources\MedicalRecordResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class DoctorMedicalRecordController extends BaseController
{
    public function __construct(
        private readonly AccessGuard $accessGuard,
    ) {}

    public function show(Request $request, int $appointmentId): JsonResponse
    {
        $doctor = $request->user()->doctor;

        if (! $doctor) {
            throw new NotFoundException('Doctor profile not found for this account.');
        }

        $appointment = Appointment::with('patient.user')->find($appointmentId);

        if (! $appointment) {
            throw new NotFoundException('Appointment not found.');
        }

        if ($appointment->doctor_id !== $doctor->id) {
            throw new AuthorizationException('This appointment does not belong to you.');
        }

        $patient = $appointment->patient;

        $level = $this->accessGuard->levelFor($doctor, $patient,$appointment);

        if ($level === null) {
            throw new AuthorizationException(
                'You do not currently have access to this patient\'s medical record.'
            );
        }

        $patientRecord = PatientRecord::where('patient_id', $patient->id)->first();

        if (! $patientRecord) {
            throw new NotFoundException('Medical record not found for this patient.');
        }

        $patientRecord->load([
            'medicalHistory.allergies',
            'medicalHistory.chronicConditions',
            'medicalHistory.surgeries',
            'medicalHistory.familyHistories',
            'medications.drug',
            'attachments',
            'encounters.clinicalNotes',
            'encounters.diagnoses',
            'encounters.prescription.items.drug',
        ]);

        $access = $this->accessGuard->resolveActiveAccess($doctor, $patient,$appointment);

        if ($access) {
            $this->accessGuard->logAccess($access, AccessAction::ViewRecord, Appointment::class, $appointment->id);
        }

        return $this->successResponse([
            'access_level' => $level->value,
            'patient'      => $this->patientSummary($patient),
            'medical_record' => new MedicalRecordResource($patientRecord),
        ], 'Medical record retrieved successfully.');
    }


    private function patientSummary(\App\Models\Patient $patient): array
    {
        $user = $patient->user;

        return [
            'id'         => $patient->id,
            'name'       => $user ? trim("{$user->first_name} {$user->last_name}") : null,
            'age'        => $user?->dob ? (int) $user->dob->diffInYears(now()) : null,
            'gender'     => $user?->gender?->value,
            'blood_type' => $patient->blood_type,
        ];
    }
}
