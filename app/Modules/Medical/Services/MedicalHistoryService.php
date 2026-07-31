<?php

namespace App\Modules\Medical\Services;

use App\Core\Exceptions\NotFoundException;
use App\Core\Services\BaseService;
use App\Models\Allergy;
use App\Models\ChronicCondition;
use App\Models\FamilyHistory;
use App\Models\MedicalHistory;
use App\Models\PatientRecord;
use App\Models\Surgery;


class MedicalHistoryService extends BaseService
{

    public function forPatientRecord(PatientRecord $patientRecord): MedicalHistory
    {
        $history = $patientRecord->medicalHistory;

        if (! $history) {
            throw new NotFoundException('Medical history not found for this patient record.');
        }

        return $history->load(['allergies', 'chronicConditions', 'surgeries', 'familyHistories']);
    }

    // ─────────────────────────────────────────────────────────────
    //  Allergies
    // ─────────────────────────────────────────────────────────────

    public function addAllergy(MedicalHistory $history, array $data): Allergy
    {
        return $this->transaction(function () use ($history, $data) {
            $allergy = Allergy::create([
                'medical_history_id' => $history->id,
                'allergen'           => $data['allergen'],
                'allergen_type'       => $data['allergen_type'],
                'reaction'           => $data['reaction'] ?? null,
                'severity'           => $data['severity'] ?? null,
            ]);

            $this->touchHistory($history);

            return $allergy;
        });
    }

    public function updateAllergy(MedicalHistory $history, Allergy $allergy, array $data): Allergy
    {
        $this->ensureBelongsToHistory($allergy->medical_history_id, $history);

        return $this->transaction(function () use ($history, $allergy, $data) {
            $allergy->update([
                'allergen' => $data['allergen'] ?? $allergy->allergen,
                'allergen_type' => $data['allergen_type']?? $allergy->allergen_type,
                'reaction' => $data['reaction'] ?? $allergy->reaction,
                'severity' => $data['severity'] ?? $allergy->severity,
            ]);

            $this->touchHistory($history);

            return $allergy->fresh();
        });
    }

    public function deleteAllergy(MedicalHistory $history, Allergy $allergy): void
    {
        $this->ensureBelongsToHistory($allergy->medical_history_id, $history);

        $this->transaction(function () use ($history, $allergy) {
            $allergy->delete();
            $this->touchHistory($history);
        });
    }

    // ─────────────────────────────────────────────────────────────
    //  Chronic Conditions
    // ─────────────────────────────────────────────────────────────

    public function addChronicCondition(MedicalHistory $history, array $data): ChronicCondition
    {
        return $this->transaction(function () use ($history, $data) {
            $condition = ChronicCondition::create([
                'medical_history_id' => $history->id,
                'condition_name'     => $data['condition_name'],
                'diagnosed_at'       => $data['diagnosed_at'] ?? null,
                'notes'              => $data['notes'] ?? null,
            ]);

            $this->touchHistory($history);

            return $condition;
        });
    }

    public function updateChronicCondition(MedicalHistory $history, ChronicCondition $condition, array $data): ChronicCondition
    {
        $this->ensureBelongsToHistory($condition->medical_history_id, $history);

        return $this->transaction(function () use ($history, $condition, $data) {
            $condition->update([
                'condition_name' => $data['condition_name'] ?? $condition->condition_name,
                'diagnosed_at'   => $data['diagnosed_at'] ?? $condition->diagnosed_at,
                'notes'          => $data['notes'] ?? $condition->notes,
            ]);

            $this->touchHistory($history);

            return $condition->fresh();
        });
    }

    public function deleteChronicCondition(MedicalHistory $history, ChronicCondition $condition): void
    {
        $this->ensureBelongsToHistory($condition->medical_history_id, $history);

        $this->transaction(function () use ($history, $condition) {
            $condition->delete();
            $this->touchHistory($history);
        });
    }

    // ─────────────────────────────────────────────────────────────
    //  Surgeries
    // ─────────────────────────────────────────────────────────────

    public function addSurgery(MedicalHistory $history, array $data): Surgery
    {
        return $this->transaction(function () use ($history, $data) {
            $surgery = Surgery::create([
                'medical_history_id' => $history->id,
                'surgery_name'       => $data['surgery_name'],
                'surgery_date'       => $data['surgery_date'] ?? null,
                'notes'              => $data['notes'] ?? null,
            ]);

            $this->touchHistory($history);

            return $surgery;
        });
    }

    public function updateSurgery(MedicalHistory $history, Surgery $surgery, array $data): Surgery
    {
        $this->ensureBelongsToHistory($surgery->medical_history_id, $history);

        return $this->transaction(function () use ($history, $surgery, $data) {
            $surgery->update([
                'surgery_name' => $data['surgery_name'] ?? $surgery->surgery_name,
                'surgery_date' => $data['surgery_date'] ?? $surgery->surgery_date,
                'notes'        => $data['notes'] ?? $surgery->notes,
            ]);

            $this->touchHistory($history);

            return $surgery->fresh();
        });
    }

    public function deleteSurgery(MedicalHistory $history, Surgery $surgery): void
    {
        $this->ensureBelongsToHistory($surgery->medical_history_id, $history);

        $this->transaction(function () use ($history, $surgery) {
            $surgery->delete();
            $this->touchHistory($history);
        });
    }

    // ─────────────────────────────────────────────────────────────
    //  Family History
    // ─────────────────────────────────────────────────────────────

    public function addFamilyHistory(MedicalHistory $history, array $data): FamilyHistory
    {
        return $this->transaction(function () use ($history, $data) {
            $entry = FamilyHistory::create([
                'medical_history_id' => $history->id,
                'condition'          => $data['condition'],
                'relation'           => $data['relation'],
                'notes'              => $data['notes'] ?? null,
            ]);

            $this->touchHistory($history);

            return $entry;
        });
    }

    public function updateFamilyHistory(MedicalHistory $history, FamilyHistory $entry, array $data): FamilyHistory
    {
        $this->ensureBelongsToHistory($entry->medical_history_id, $history);

        return $this->transaction(function () use ($history, $entry, $data) {
            $entry->update([
                'condition' => $data['condition'] ?? $entry->condition,
                'relation'  => $data['relation'] ?? $entry->relation,
                'notes'     => $data['notes'] ?? $entry->notes,
            ]);

            $this->touchHistory($history);

            return $entry->fresh();
        });
    }

    public function deleteFamilyHistory(MedicalHistory $history, FamilyHistory $entry): void
    {
        $this->ensureBelongsToHistory($entry->medical_history_id, $history);

        $this->transaction(function () use ($history, $entry) {
            $entry->delete();
            $this->touchHistory($history);
        });
    }

    // ─────────────────────────────────────────────────────────────
    //  Private Helpers
    // ─────────────────────────────────────────────────────────────


    private function touchHistory(MedicalHistory $history): void
    {
        $history->update(['recorded_at' => now()]);
    }

    private function ensureBelongsToHistory(int $actualHistoryId, MedicalHistory $expectedHistory): void
    {
        if ($actualHistoryId !== $expectedHistory->id) {
            throw new NotFoundException('Record not found in your medical history.');
        }
    }
}
