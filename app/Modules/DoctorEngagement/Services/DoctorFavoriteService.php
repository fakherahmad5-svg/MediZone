<?php

namespace App\Modules\DoctorEngagement\Services;

use App\Core\Exceptions\NotFoundException;
use App\Core\Services\BaseService;
use App\Models\Doctor;
use App\Models\DoctorFavorite;
use App\Models\Patient;
use App\Models\User;


class DoctorFavoriteService extends BaseService
{

    public function toggle(User $patientUser, int $doctorId): array
    {
        $patient = $this->patientFor($patientUser);

        if (! Doctor::find($doctorId)) {
            throw new NotFoundException('Doctor not found.');
        }

        return $this->transaction(function () use ($patient, $doctorId) {
            $existing = DoctorFavorite::where('patient_id', $patient->id)
                ->where('doctor_id', $doctorId)
                ->first();

            if ($existing) {
                $existing->delete();

                return ['is_favorite' => false];
            }

            DoctorFavorite::create([
                'patient_id' => $patient->id,
                'doctor_id'  => $doctorId,
            ]);

            return ['is_favorite' => true];
        });
    }

    public function listForPatient(User $patientUser)
    {
        $patient = $this->patientFor($patientUser);

        return DoctorFavorite::where('patient_id', $patient->id)
            ->with(['doctor.user', 'doctor.profile'])
            ->latest('id')
            ->get();
    }

    private function patientFor(User $user): Patient
    {
        $patient = $user->patient;

        if (! $patient) {
            throw new NotFoundException('Patient profile not found for this account.');
        }

        return $patient;
    }
}
