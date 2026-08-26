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
            'gender'           => $this->user?->gender?->value,
            'photo_url'        => $this->photoUrl(),
            'experience_years' => $this->experience_years,

            'biography'        => $this->whenLoaded('profile', fn () => $this->profile?->biography),
            'qualifications'   => $this->whenLoaded('profile', fn () => $this->profile?->qualifications),
            'online_consultation_fee' => $this->whenLoaded('profile', fn () => $this->profile?->online_consultation_fee),
            'languages'        => $this->whenLoaded('profile', fn () => $this->profile?->languages),

            'departments' => DepartmentResource::collection($this->whenLoaded('departments')),
            'clinics'     => $this->whenLoaded('clinics', fn () => $this->clinics->map(fn ($clinic) => [
                ...(new ClinicResource($clinic))->resolve(),
                'consultation_fee' => $clinic->pivot->consultation_fee,
            ])),
        ];
    }
}
