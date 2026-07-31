<?php

namespace App\Modules\Medical\Resources;

use App\Core\Http\Resources\BaseResource;

/**
 * @mixin \App\Models\Surgery
 */
class SurgeryResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'id'           => $this->id,
            'surgery_name' => $this->surgery_name,
            'surgery_date' => $this->formatDateOnly($this->surgery_date),
            'notes'        => $this->notes,
            'created_at'   => $this->formatDate($this->created_at),
        ];
    }
}
