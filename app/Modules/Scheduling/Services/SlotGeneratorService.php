<?php

namespace App\Modules\Scheduling\Services;

use App\Core\Enums\SlotStatus;
use App\Core\Exceptions\BusinessException;
use App\Core\Services\BaseService;
use App\Models\BlockedTime;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorTimeSlot;
use App\Models\ScheduleConfig;
use Carbon\Carbon;
use Carbon\CarbonPeriod;


class SlotGeneratorService extends BaseService
{
    private const MAX_GENERATION_DAYS = 90;


    public function generate(Doctor $doctor, Clinic $clinic, Carbon $from, Carbon $to): int
    {
        if ($to->lessThan($from)) {
            throw new BusinessException('End date must be after start date.');
        }

        if ($from->diffInDays($to) > self::MAX_GENERATION_DAYS) {
            throw new BusinessException(
                'Cannot generate slots for more than ' . self::MAX_GENERATION_DAYS . ' days at once.'
            );
        }

        $config = ScheduleConfig::where('doctor_id', $doctor->id)
            ->where('clinic_id', $clinic->id)
            ->with('days.sessions')
            ->first();

        if (! $config || ! $config->is_active) {
            return 0;
        }

        $blockedTimes = BlockedTime::where('doctor_id', $doctor->id)
            ->where('clinic_id', $clinic->id)
            ->get();

        $createdCount = 0;

        foreach (CarbonPeriod::create($from->startOfDay(), $to->endOfDay()) as $date) {
            // إجازة نشطة تغطي هذا اليوم بالذات — تخطَّه فقط، وليس كل
            // نطاق التوليد (قد يكون from/to أوسع من فترة الإجازة).
            if ($this->isWithinVacation($config, $date)) {
                continue;
            }

            $day = $config->days->firstWhere('day_of_week', $date->dayOfWeek);

            if (! $day || ! $day->is_active) {
                continue;
            }

            foreach ($day->sessions->where('is_active', true) as $session) {
                $createdCount += $this->generateSlotsForSession(
                    $doctor,
                    $clinic,
                    $session,
                    $date,
                    $config->stepMinutes(),
                    $config->consultation_duration,
                    $blockedTimes
                );
            }
        }

        return $createdCount;
    }

    private function isWithinVacation(ScheduleConfig $config, Carbon $date): bool
    {
        if (! $config->vacation_start_date || ! $config->vacation_end_date) {
            return false;
        }

        return $date->between(
            $config->vacation_start_date->copy()->startOfDay(),
            $config->vacation_end_date->copy()->endOfDay()
        );
    }

    /**
     * @param \Illuminate\Support\Collection<int, BlockedTime> $blockedTimes
     */
    private function generateSlotsForSession(
        Doctor $doctor,
        Clinic $clinic,
        \App\Models\ScheduleSession $session,
        Carbon $date,
        int $stepMinutes,
        int $consultationDuration,
        \Illuminate\Support\Collection $blockedTimes,
    ): int {
        $sessionStart = $date->copy()->setTimeFromTimeString($session->start_time);
        $sessionEnd   = $date->copy()->setTimeFromTimeString($session->end_time);

        $created = 0;
        $cursor  = $sessionStart->copy();

        while ($cursor->copy()->addMinutes($consultationDuration)->lessThanOrEqualTo($sessionEnd)) {
            $slotStart = $cursor->copy();
            $slotEnd   = $cursor->copy()->addMinutes($consultationDuration);

            $cursor->addMinutes($stepMinutes);

            if ($slotStart->isPast()) {
                continue;
            }

            $isBlocked = $blockedTimes->contains(
                fn (BlockedTime $blocked) => $blocked->overlaps(
                    $date,
                    $slotStart->format('H:i:s'),
                    $slotEnd->format('H:i:s')
                )
            );

            if ($isBlocked) {
                continue;
            }

            $slot = DoctorTimeSlot::firstOrCreate(
                [
                    'doctor_id' => $doctor->id,
                    'clinic_id' => $clinic->id,
                    'starts_at' => $slotStart,
                ],
                [
                    'session_id' => $session->id,
                    'ends_at'    => $slotEnd,
                    'status'     => SlotStatus::Available->value,
                ]
            );

            if ($slot->wasRecentlyCreated) {
                $created++;
            }
        }

        return $created;
    }
}
