<?php

namespace App\Modules\Scheduling\Resources;

use App\Core\Http\Resources\BaseResource;

/**
 * @mixin \App\Models\ScheduleSession
 */
class ScheduleSessionResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'id'           => $this->id,
            'session_type' => $this->session_type,
            'start_time'   => $this->start_time,
            'end_time'     => $this->end_time,
        ];
    }
}
