<?php

namespace App\Modules\Doctors\Resources;

use App\Core\Http\Resources\BaseResource;
use App\Modules\Clinics\Resources\ClinicResource;
use App\Modules\Departments\Resources\DepartmentResource;


class DoctorSelfResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,


            'account' => $this->whenLoaded('user', fn () => [
                'first_name' => $this->user->first_name,
                'last_name'  => $this->user->last_name,
                'full_name'  => trim("{$this->user->first_name} {$this->user->last_name}"),
                'email'      => $this->user->email,
                'phone'      => $this->user->phone,
                'dob'        => $this->user->dob?->toDateString(),
                'gender'     => $this->user->gender,
                'address'    => $this->user->address,
                'status'     => $this->user->status,
                'created_at' => $this->user->created_at?->toIso8601String(),
            ]),

            'verification' => [
                'status'         => $this->verification_status->value,
            ],

            'career' => [
                'practice_start_date' => $this->practice_start_date,
                'experience_years'    => $this->experience_years,
            ],


            'documents' => [
                'photo_url'        => $this->photoUrl(),
                'license_file_url' => $this->licenseFileUrl(),
                'id_card_url'      => $this->idCardUrl(),
                'certificate_urls' => $this->certificateUrls(),
            ],


            'profile' => $this->whenLoaded('profile', fn () => [
                'biography'        => $this->profile?->biography,
                'online_consultation_fee' => $this->profile?->online_consultation_fee,
                'languages'        => $this->profile?->languages,
                'qualifications'   => $this->profile?->qualifications,
            ]),

            'departments' => DepartmentResource::collection($this->whenLoaded('departments')->unique('name')),
            'clinics'     => $this->whenLoaded('clinics', fn () => $this->clinics->map(fn ($clinic) => [
                ...(new ClinicResource($clinic))->resolve(),
                'consultation_fee' => $clinic->pivot->consultation_fee,
            ])),
        ];
    }
}
