<?php

namespace App\Modules\DoctorEngagement\Resources;

use App\Core\Http\Resources\BaseResource;

/**
 * @mixin \App\Models\DoctorReview
 */
class DoctorReviewResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'id'      => $this->id,
            'rating'  => $this->rating,
            'comment' => $this->comment,
            'patient' => $this->whenLoaded('patient', fn () => [
                'name' => trim("{$this->patient?->user?->first_name} {$this->patient?->user?->last_name}"),
            ]),
            'created_at' => $this->formatDate($this->created_at),
        ];
    }
}
