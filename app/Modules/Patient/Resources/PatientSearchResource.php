<?php

namespace App\Modules\Patient\Resources;

use App\Core\Http\Resources\BaseResource;

class PatientSearchResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'name'           => trim("{$this->user?->first_name} {$this->user?->last_name}"),
            'phone'          => $this->user?->phone,
            'id_card_number' => $this->user?->ID_card_number,
            'gender'         => $this->user?->gender?->value ?? $this->user?->gender,
            'dob'            => $this->formatDate($this->user?->dob, 'date'),
        ];
    }
}
