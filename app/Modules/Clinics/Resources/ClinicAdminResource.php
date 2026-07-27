<?php

namespace App\Modules\Clinics\Resources;

use App\Core\Http\Resources\BaseResource;
use App\Modules\Departments\Resources\DepartmentResource;


class ClinicAdminResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'phone'         => $this->phone,
            'email'         => $this->email,
            'address'       => $this->address,
            'latitude'      => $this->latitude,
            'longitude'     => $this->longitude,
            'status'        => $this->status->value,
            'review_notes'  => $this->review_notes,
            'license_url'   => $this->licenseUrl(),

            'owner' => $this->whenLoaded('owner', fn () => [
                'id'    => $this->owner?->id,
                'name'  => trim("{$this->owner?->first_name} {$this->owner?->last_name}"),
                'email' => $this->owner?->email,
            ]),

            'departments' => DepartmentResource::collection($this->whenLoaded('departments')),

            'created_at' => $this->formatDate($this->created_at),
        ];
    }
}
