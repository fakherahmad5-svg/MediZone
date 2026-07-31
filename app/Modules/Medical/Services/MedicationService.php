<?php

namespace App\Modules\Medical\Services;

use App\Core\Enums\MedicationSource;
use App\Core\Enums\MedicationStatus;
use App\Core\Exceptions\AuthorizationException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Services\BaseService;
use App\Models\Drug;
use App\Models\Medication;
use App\Models\PatientRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;


class MedicationService extends BaseService
{
    public function listForPatientRecord(PatientRecord $patientRecord): Collection
    {
        return $patientRecord->medications()
            ->with('drug')
            ->latest('id')
            ->get();
    }


    public function searchDrugs(string $query): Collection
    {
        return Drug::where('name', 'like', "%{$query}%")
            ->orderBy('name')
            ->limit(10)
            ->get();
    }


    public function addSelfReported(PatientRecord $patientRecord, User $patient, array $data): Medication
    {
        return $this->transaction(function () use ($patientRecord, $patient, $data) {
            $drug = Drug::firstOrCreate(
                ['name' => $data['drug_name']],
                ['form' => $data['form'] ?? null, 'strength' => $data['strength'] ?? null]
            );

            return Medication::create([
                'patient_record_id' => $patientRecord->id,
                'drug_id'           => $drug->id,
                'source'            => MedicationSource::SelfReported->value,
                'status'            => MedicationStatus::Active->value,
                'dosage'            => $data['dosage'],
                'frequency'         => $data['frequency'],
                'route'             => $data['route'] ?? null,
                'start_date'        => $data['start_date'] ?? now()->toDateString(),
                'recorded_by'       => $patient->id,
                'notes'             => $data['notes'] ?? null,
            ]);
        });
    }


    public function update(PatientRecord $patientRecord, Medication $medication, array $data): Medication
    {
        $this->ensureOwnedAndEditable($patientRecord, $medication);

        return $this->transaction(function () use ($medication, $data) {
            $medication->update(array_filter([
                'dosage'    => $data['dosage'] ?? null,
                'frequency' => $data['frequency'] ?? null,
                'route'     => $data['route'] ?? null,
                'notes'     => $data['notes'] ?? null,
            ], fn ($v) => $v !== null));

            return $medication->fresh('drug');
        });
    }

    public function stop(PatientRecord $patientRecord, Medication $medication, string $reason): Medication
    {
        $this->ensureOwnedAndEditable($patientRecord, $medication);

        return $this->transaction(function () use ($medication, $reason) {
            $medication->update([
                'status'      => MedicationStatus::Stopped->value,
                'stopped_at'  => now(),
                'stop_reason' => $reason,
            ]);

            return $medication->fresh('drug');
        });
    }


    public function delete(PatientRecord $patientRecord, Medication $medication): void
    {
        $this->ensureOwnedAndEditable($patientRecord, $medication);

        $medication->delete();
    }

    // ─────────────────────────────────────────────────────────────


    private function ensureOwnedAndEditable(PatientRecord $patientRecord, Medication $medication): void
    {
        if ($medication->patient_record_id !== $patientRecord->id) {
            throw new NotFoundException('Medication not found in your record.');
        }

        if (! $medication->isEditableByPatient()) {
            throw new AuthorizationException(
                'This medication was recorded by a doctor and cannot be modified by the patient.'
            );
        }
    }
}
