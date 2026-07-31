<?php

namespace App\Core\Traits;

use App\Core\Exceptions\NotFoundException;
use App\Models\PatientRecord;
use App\Models\User;

trait ResolvesPatientRecord
{

    protected function resolvePatientRecord(User $user): PatientRecord
    {
        $patientRecord = $user->patient?->patientRecord;

        if (! $patientRecord) {
            throw new NotFoundException('Medical record not found for this account.');
        }

        $this->authorize('view', $patientRecord);

        return $patientRecord;
    }
}
