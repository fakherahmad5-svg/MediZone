<?php

namespace App\Modules\Departments\Resources;

use App\Core\Http\Resources\BaseResource;

/**
 * DepartmentResource
 *
 * @mixin \App\Models\Department
 */
class DepartmentResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'description'     => $this->description,
            'doctors_count'   => $this->whenCounted('doctors'),
            'clinics_count'   => $this->whenCounted('clinics'),
            'created_at'      => $this->formatDate($this->created_at),
        ];
    }
}
