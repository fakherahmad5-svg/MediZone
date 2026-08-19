<?php

namespace App\Modules\Encounters\Resources;

use App\Core\Http\Resources\BaseResource;

/**
 * @mixin \App\Models\Encounter
 */
class EncounterResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'id'              => $this->id,
            'appointment_id'  => $this->appointment_id,
            'visit_type'      => $this->visit_type,
            'clinical_notes'  => ClinicalNoteResource::collection($this->whenLoaded('clinicalNotes')),
            'diagnoses'       => DiagnosisResource::collection($this->whenLoaded('diagnoses')),
            'prescription'    => $this->whenLoaded('prescription', fn () => $this->prescription
                ? new PrescriptionResource($this->prescription)
                : null),
            'created_at'      => $this->formatDate($this->created_at),
        ];
    }
}
