<?php

namespace App\Modules\Scheduling\Resources;

use App\Core\Http\Resources\BaseResource;

/**
 * @mixin \App\Models\ScheduleDay
 */
class ScheduleDayResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'day_of_week' => $this->day_of_week,
            'sessions'    => ScheduleSessionResource::collection($this->whenLoaded('sessions')),
        ];
    }
}
