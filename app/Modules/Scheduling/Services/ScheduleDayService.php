<?php

namespace App\Modules\Scheduling\Services;

use App\Models\ScheduleConfig;
use App\Models\ScheduleDay;
use App\Models\ScheduleSession;
use Illuminate\Support\Facades\DB;

class ScheduleDayService
{
    public function storeDays(ScheduleConfig $config, array $data): ScheduleConfig
    {
        return DB::transaction(function () use ($config, $data) {
            foreach ($data['days'] as $dayData) {
                $day = ScheduleDay::updateOrCreate(
                    ['schedule_config_id' => $config->id, 'day_of_week' => $dayData['day_of_week']],
                    ['is_active' => true]
                );
                $day->sessions()->delete();
                foreach ($dayData['sessions'] as $sessionData) {
                    ScheduleSession::create([
                        'schedule_day_id' => $day->id,
                        'session_type'    => $sessionData['session_type'],
                        'start_time'      => $sessionData['start_time'],
                        'end_time'        => $sessionData['end_time'],
                        'is_active'       => true,
                    ]);
                }
            }
            return $config->load('days.sessions');
        });
    }

    public function getDays(ScheduleConfig $config): ScheduleConfig
    {
        return $config->load('days.sessions');
    }
}
