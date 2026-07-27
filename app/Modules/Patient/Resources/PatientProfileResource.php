<?php

namespace App\Modules\Patient\Resources;

use App\Core\Http\Resources\BaseResource;


class PatientProfileResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'blood_type' => $this->blood_type,
            'created_at' => $this->formatDate($this->created_at),
        ];
    }
}
