<?php

namespace App\Modules\DoctorEngagement\Resources;

use App\Core\Http\Resources\BaseResource;


class DoctorFavoriteResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'doctor' => $this->whenLoaded('doctor', fn () => [
                'id'   => $this->doctor?->id,
                'name' => trim("{$this->doctor?->user?->first_name} {$this->doctor?->user?->last_name}"),
            ]),
            'created_at' => $this->formatDate($this->created_at),
        ];
    }
}
