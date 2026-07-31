<?php

namespace App\Observers;

use App\Models\MedicalHistory;
use App\Models\Patient;
use App\Models\PatientRecord;

class PatientObserver
{

    public function created(Patient $patient): void
    {
        $record = PatientRecord::firstOrCreate([
            'patient_id' => $patient->id,
        ]);

        MedicalHistory::firstOrCreate([
            'patient_record_id' => $record->id,
        ]);
    }
}
