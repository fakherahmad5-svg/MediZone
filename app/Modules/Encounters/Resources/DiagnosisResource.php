<?php

namespace App\Modules\Encounters\Resources;

use App\Core\Http\Resources\BaseResource;

/**
 * @mixin \App\Models\Diagnosis
 */
class DiagnosisResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'label'       => $this->label,
            'description' => $this->description,
            'doctor_id'   => $this->doctor_id,
            'created_at'  => $this->formatDate($this->created_at),
        ];
    }
}
