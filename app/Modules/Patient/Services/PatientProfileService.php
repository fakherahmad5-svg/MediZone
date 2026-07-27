<?php

namespace App\Modules\Patient\Services;

use App\Core\Exceptions\NotFoundException;
use App\Core\Services\BaseService;
use App\Models\Patient;
use App\Models\User;


class PatientProfileService extends BaseService
{

    public function forUser(User $user): Patient
    {
        $patient = $user->patient;

        if (! $patient) {
            throw new NotFoundException('Patient profile not found for this account.');
        }

        return $patient->load('patientRecord');
    }

    public function updateProfile(Patient $patient, array $data): Patient
    {
        return $this->transaction(function () use ($patient, $data) {
            $patient->update([
                'blood_type' => $data['blood_type'] ?? $patient->blood_type,
            ]);

            return $patient->fresh();
        });
    }
}
