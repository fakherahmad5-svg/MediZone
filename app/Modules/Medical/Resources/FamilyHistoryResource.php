<?php

namespace App\Modules\Medical\Resources;

use App\Core\Http\Resources\BaseResource;

/**
 * @mixin \App\Models\FamilyHistory
 */
class FamilyHistoryResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'condition'  => $this->condition,
            'relation'   => $this->relation,
            'notes'      => $this->notes,
            'created_at' => $this->formatDate($this->created_at),
        ];
    }
}
