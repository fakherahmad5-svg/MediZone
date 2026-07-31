<?php

namespace App\Modules\Medical\Resources;

use App\Core\Http\Resources\BaseResource;

/**
 * @mixin \App\Models\PatientRecord
 */
class MedicalRecordResource extends BaseResource
{
    public function toArray($request): array
    {
        $history = $this->medicalHistory;

        return [
            'patient_record_id' => $this->id,
            'recorded_at'       => $this->formatDate($history?->recorded_at),

            'medical_history'   =>[
            'allergies' => AllergyResource::collection(
                $history?->relationLoaded('allergies') ? $history->allergies : []
            ),
            'chronic_conditions' => ChronicConditionResource::collection(
                $history?->relationLoaded('chronicConditions') ? $history->chronicConditions : []
            ),
            'surgeries' => SurgeryResource::collection(
                $history?->relationLoaded('surgeries') ? $history->surgeries : []
            ),
            'family_history' => FamilyHistoryResource::collection(
                $history?->relationLoaded('familyHistories') ? $history->familyHistories : []
            ),
],
            'medications' => MedicationResource::collection($this->whenLoaded('medications')),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
        ];
    }
}
