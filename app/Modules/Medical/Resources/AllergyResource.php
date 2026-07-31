<?php

namespace App\Modules\Medical\Resources;

use App\Core\Http\Resources\BaseResource;


class AllergyResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'allergen'   => $this->allergen,
            'allergen_type' => $this->allergen_type,
            'reaction'   => $this->reaction,
            'severity'   => $this->severity?->value,
            'created_at' => $this->formatDate($this->created_at),
        ];
    }
}
