<?php

namespace App\Modules\Clinics\Resources;

use App\Core\Http\Resources\BaseResource;
use App\Modules\Departments\Resources\DepartmentResource;


class ClinicResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'phone'       => $this->phone,
            'email'       => $this->email,
            'address'     => $this->address,
            'latitude'    => $this->latitude,
            'longitude'   => $this->longitude,
            'status'      => $this->status->value,
            'departments' => DepartmentResource::collection($this->whenLoaded('departments')),
        ];
    }
}
