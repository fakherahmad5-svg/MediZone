<?php

namespace App\Modules\Medical\Resources;

use App\Core\Http\Resources\BaseResource;

/**
 * @mixin \App\Models\Medication
 */
class MedicationResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'drug_name'  => $this->whenLoaded('drug', fn () => $this->drug?->name),
            'form'       => $this->whenLoaded('drug', fn () => $this->drug?->form),
            'strength'   => $this->whenLoaded('drug', fn () => $this->drug?->strength),
            'source'     => $this->source->value,
            'status'     => $this->status->value,
            'dosage'     => $this->dosage,
            'frequency'  => $this->frequency,
            'route'      => $this->route,
            'start_date' => $this->formatDateOnly($this->start_date),
            'end_date'   => $this->formatDateOnly($this->end_date),
            'stopped_at' => $this->formatDateOnly($this->stopped_at),
            'stop_reason'=> $this->stop_reason,
            'notes'      => $this->notes,
            'editable'   => $this->isEditableByPatient(),
            'created_at' => $this->formatDate($this->created_at),
        ];
    }
}
