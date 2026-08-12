<?php

namespace App\Modules\Scheduling\Resources;

use App\Core\Http\Resources\BaseResource;

/**
 * @mixin \App\Models\ScheduleConfig
 */
class ScheduleConfigResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'id'                     => $this->id,
            'clinic_id'              => $this->clinic_id,
            'clinic_name'            => $this->whenLoaded('clinic', fn () => $this->clinic?->name),
            'consultation_duration'  => $this->consultation_duration,
            'break_duration'         => $this->break_duration,
            'buffer_enabled'         => $this->buffer_enabled,
            'max_patients'           => $this->max_patients,
            'vacation_start_date'    => $this->formatDateOnly($this->vacation_start_date),
            'vacation_end_date'      => $this->formatDateOnly($this->vacation_end_date),
            'is_on_vacation'         => $this->vacation_start_date !== null
                && $this->vacation_end_date !== null
                && now()->between(
                    $this->vacation_start_date->copy()->startOfDay(),
                    $this->vacation_end_date->copy()->endOfDay()
                ),
            'is_active'              => $this->is_active,
            'days'                   => ScheduleDayResource::collection($this->whenLoaded('days')),
        ];
    }
}
