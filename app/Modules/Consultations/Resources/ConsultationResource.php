<?php

namespace App\Modules\Consultations\Resources;

use App\Core\Http\Resources\BaseResource;

/**
 * @mixin \App\Models\Consultation
 */
class ConsultationResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'appointment_id' => $this->appointment_id,
            'status'         => $this->status,
            'started_at'     => $this->formatDate($this->started_at),
            'ended_at'       => $this->formatDate($this->ended_at),
        ];
    }
}
