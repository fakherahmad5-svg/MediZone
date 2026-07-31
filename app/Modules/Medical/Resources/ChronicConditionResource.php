<?php

namespace App\Modules\Medical\Resources;

use App\Core\Http\Resources\BaseResource;

/**
 * @mixin \App\Models\ChronicCondition
 */
class ChronicConditionResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'condition_name' => $this->condition_name,
            'diagnosed_at'   => $this->formatDateOnly($this->diagnosed_at),
            'notes'          => $this->notes,
            'created_at'     => $this->formatDate($this->created_at),
        ];
    }
}
