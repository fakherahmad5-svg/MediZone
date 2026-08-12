<?php

namespace App\Modules\Scheduling\Resources;

use App\Core\Http\Resources\BaseResource;

/**
 * @mixin \App\Models\BlockedTime
 */
class BlockedTimeResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'block_date'  => $this->formatDateOnly($this->block_date),
            'day_of_week' => $this->day_of_week,
            'start_time'  => $this->start_time,
            'end_time'    => $this->end_time,
            'reason'      => $this->reason,
            'created_at'  => $this->formatDate($this->created_at),
        ];
    }
}
