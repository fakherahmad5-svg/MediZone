<?php

namespace App\Modules\Encounters\Resources;

use App\Core\Http\Resources\BaseResource;

/**
 * @mixin \App\Models\ClinicalNote
 */
class ClinicalNoteResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'content'    => $this->content,
            'doctor_id'  => $this->doctor_id,
            'created_at' => $this->formatDate($this->created_at),
        ];
    }
}
