<?php

namespace App\Modules\Access\Resources;

use App\Core\Http\Resources\BaseResource;

/**
 * @mixin \App\Models\PatientDoctorAccess
 */
class PatientDoctorAccessResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'status'         => $this->status->value,
            'appointment_id' => $this->appointment_id,

            'doctor' => $this->whenLoaded('doctor', fn () => [
                'id'   => $this->doctor?->id,
                'name' => trim("{$this->doctor?->user?->first_name} {$this->doctor?->user?->last_name}"),
            ]),

            'appointment' => $this->whenLoaded('appointment', fn () => [
                'id'     => $this->appointment?->id,
                'status' => $this->appointment?->status?->value,
                'starts_at' => $this->when(
                    $this->appointment?->slot,
                    fn () => $this->formatDate($this->appointment->slot->starts_at)
                ),
            ]),

            'granted_at' => $this->when($this->granted_at, fn () => $this->formatDate($this->granted_at)),
        ];
    }
}
