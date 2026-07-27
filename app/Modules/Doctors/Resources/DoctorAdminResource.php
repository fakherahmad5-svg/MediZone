<?php

namespace App\Modules\Doctors\Resources;

use App\Core\Http\Resources\BaseResource;
use App\Modules\Departments\Resources\DepartmentResource;


class DoctorAdminResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'id'                  => $this->id,
            'verification_status' => $this->verification_status->value,


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

            'documents' => [
                'photo_url'        => $this->photoUrl(),
                'license_file_url' => $this->licenseFileUrl(),
                'id_card_url'      => $this->idCardUrl(),
                'certificate_urls' => $this->certificateUrls(),
            ],

            'profile' => $this->whenLoaded('profile', fn () => [
                'biography'        => $this->profile?->biography,
                'qualifications'   => $this->profile?->qualifications,
                'consultation_fee' => $this->profile?->consultation_fee,
            ]),

            'departments' => DepartmentResource::collection($this->whenLoaded('departments')),

            'created_at' => $this->formatDate($this->created_at),
        ];
    }
}
