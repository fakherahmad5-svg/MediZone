<?php

namespace App\Modules\Encounters\Resources;

use App\Core\Http\Resources\BaseResource;

/**
 * @mixin \App\Models\Prescription
 */
class PrescriptionResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'id'    => $this->id,
            'notes' => $this->notes,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id'        => $item->id,
                'drug'      => $item->drug?->name,
                'dosage'    => $item->dosage,
                'frequency' => $item->frequency,
                'duration'  => $item->duration,
                'route'     => $item->route,
                'notes'     => $item->notes,
            ])),
        ];
    }
}
