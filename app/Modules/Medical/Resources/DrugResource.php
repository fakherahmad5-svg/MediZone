<?php

namespace App\Modules\Medical\Resources;

use App\Core\Http\Resources\BaseResource;

/**
 * @mixin \App\Models\Drug
 */
class DrugResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'id'       => $this->id,
            'name'     => $this->name,
            'form'     => $this->form,
            'strength' => $this->strength,
        ];
    }
}
