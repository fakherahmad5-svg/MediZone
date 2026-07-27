<?php

namespace App\Modules\Doctors\Resources;

use App\Core\Http\Resources\BaseResource;
use App\Modules\Clinics\Resources\ClinicResource;
use App\Modules\Departments\Resources\DepartmentResource;


class DoctorPublicResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'id'               => $this->id,
            'name'             => trim("{$this->user?->first_name} {$this->user?->last_name}"),
            'photo_url'        => $this->photoUrl(),

            'biography'        => $this->whenLoaded('profile', $this->profile?->biography),
            'qualifications'   => $this->whenLoaded('profile', $this->profile?->qualifications),
            'consultation_fee' => $this->whenLoaded('profile', $this->profile?->consultation_fee),
            'languages'        => $this->whenLoaded('profile', $this->profile?->languages),

            'departments' => DepartmentResource::collection($this->whenLoaded('departments')),
            'clinics'     => ClinicResource::collection($this->whenLoaded('clinics')),
        ];
    }
}
