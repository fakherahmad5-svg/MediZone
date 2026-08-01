<?php

namespace App\Modules\Scheduling\Jobs;

use App\Models\BlockedTime;
use App\Models\DoctorTimeSlot;
use App\Models\ScheduleConfig;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateDoctorSlotsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public ScheduleConfig $config, public int $daysAhead = 14) {}

    public function handle(): void
    {
        if (!$this->config->is_active || $this->config->is_vacation_mode) return;

        $today = Carbon::today();

        foreach ($this->config->days()->where('is_active', true)->with('sessions')->get() as $day) {
            for ($i = 0; $i < $this->daysAhead; $i++) {
                $date = $today->copy()->addDays($i);
                if ($date->dayOfWeek !== $day->day_of_week) continue;

                foreach ($day->sessions()->where('is_active', true)->get() as $session) {
                    $start    = Carbon::parse($date->format('Y-m-d') . ' ' . $session->start_time);
                    $end      = Carbon::parse($date->format('Y-m-d') . ' ' . $session->end_time);
                    $duration = $this->config->consultation_duration + $this->config->break_duration;

                    while ($start->copy()->addMinutes($this->config->consultation_duration) <= $end) {
                        $slotEnd = $start->copy()->addMinutes($this->config->consultation_duration);

                        $blocked = BlockedTime::where('doctor_id', $this->config->doctor_id)
                            ->where('clinic_id', $this->config->clinic_id)
                            ->where(function ($q) use ($start, $slotEnd) {
                                $q->where('block_date', $start->toDateString())
                                  ->orWhere('day_of_week', $start->dayOfWeek);
                            })
                            ->where('start_time', '<', $slotEnd->format('H:i:s'))
                            ->where('end_time', '>', $start->format('H:i:s'))
                            ->exists();

                        if (!$blocked) {
                            DoctorTimeSlot::firstOrCreate(
                                [
                                    'doctor_id' => $this->config->doctor_id,
                                    'clinic_id' => $this->config->clinic_id,
                                    'starts_at' => $start->copy(),
                                ],
                                [
                                    'session_id' => $session->id,
                                    'ends_at'    => $slotEnd->copy(),
                                    'status'     => 'available',
                                ]
                            );
                        }

                        $start->addMinutes($duration);
                    }
                }
            }
        }
    }
}