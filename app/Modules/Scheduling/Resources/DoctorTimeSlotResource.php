<?php

namespace App\Modules\Scheduling\Resources;

use App\Core\Http\Resources\BaseResource;

/**
 * @mixin \App\Models\DoctorTimeSlot
 */
class DoctorTimeSlotResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'clinic_id'  => $this->clinic_id,
            'starts_at'  => $this->formatDate($this->starts_at),
            'ends_at'    => $this->formatDate($this->ends_at),
            'status'     => $this->status,
        ];
    }
}
