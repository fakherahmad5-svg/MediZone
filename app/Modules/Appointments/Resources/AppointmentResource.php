<?php

namespace App\Modules\Appointments\Resources;

use App\Core\Http\Resources\BaseResource;

class AppointmentResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'id'                   => $this->id,
            'status'               => $this->status->value,
            'encounter_type'       => $this->encounter_type->value,
            'price'                => $this->price,
            'notes'                => $this->notes,
            'cancellation_reason' => $this->cancellation_reason,

            'clinic' => $this->whenLoaded('clinic', fn () => [
                'id'   => $this->clinic?->id,
                'name' => $this->clinic?->name,
            ]),

            'doctor' => $this->whenLoaded('doctor', fn () => [
                'id'   => $this->doctor?->id,
                'name' => trim("{$this->doctor?->user?->first_name} {$this->doctor?->user?->last_name}"),
            ]),

            'patient' => $this->whenLoaded('patient', fn () => [
                'id'   => $this->patient?->id,
                'name' => trim("{$this->patient?->user?->first_name} {$this->patient?->user?->last_name}"),
            ]),

            'slot' => $this->whenLoaded('slot', fn () => $this->slot ? [
                'starts_at' => $this->formatDate($this->slot->starts_at),
                'ends_at'   => $this->formatDate($this->slot->ends_at),
            ] : null),

            'created_at' => $this->formatDate($this->created_at),
        ];
    }
}
