<?php

namespace App\Modules\Receptionists\Resources;

use App\Core\Http\Resources\BaseResource;


class ReceptionistAdminResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'id'           => $this->id,
            'status'       => $this->status->value,
            'review_notes' => $this->review_notes,

            'user' => $this->whenLoaded('user', fn () => [
                'id'    => $this->user?->id,
                'name'  => trim("{$this->user?->first_name} {$this->user?->last_name}"),
                'email' => $this->user?->email,
            ]),

            'clinic' => $this->when(
                $this->relationLoaded('user') && $this->user?->relationLoaded('clinicUsers'),
                fn () => optional($this->user?->clinicUsers->first())->clinic?->only(['id', 'name'])
            ),

            'created_at' => $this->formatDate($this->created_at),
        ];
    }
}
