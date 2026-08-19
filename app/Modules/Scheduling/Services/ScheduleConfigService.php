<?php

namespace App\Modules\Scheduling\Services;

use App\Core\Enums\SlotStatus;
use App\Core\Exceptions\BusinessException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Services\BaseService;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorTimeSlot;
use App\Models\ScheduleConfig;
use App\Models\ScheduleDay;
use App\Models\ScheduleSession;
use Illuminate\Support\Carbon;


class ScheduleConfigService extends BaseService
{

    public function forDoctorClinic(Doctor $doctor, int $clinicId): ScheduleConfig
    {
        $config = ScheduleConfig::where('doctor_id', $doctor->id)
            ->where('clinic_id', $clinicId)
            ->with('days.sessions')
            ->first();

        if (! $config) {
            throw new NotFoundException('No schedule configuration found for this clinic yet.');
        }

        return $config;
    }

    public function allForDoctor(Doctor $doctor)
    {
        $config = ScheduleConfig::where('doctor_id', $doctor->id)
            ->with(['clinic:id,name', 'days.sessions'])
            ->get();

        if (! $config) {
            throw new NotFoundException('No schedule configuration found for this clinic yet.');
        }
        return $config;
    }


    public function setWeeklySchedule(Doctor $doctor, Clinic $clinic, array $data): ScheduleConfig
    {
        return $this->transaction(function () use ($doctor, $clinic, $data) {
            $this->assertNoScheduleConflict($doctor, $clinic, $data['days']);

            $config = ScheduleConfig::updateOrCreate(
                ['doctor_id' => $doctor->id, 'clinic_id' => $clinic->id],
                [
                    'consultation_duration' => $data['consultation_duration'],
                    'break_duration'        => $data['break_duration'] ?? 0,
                    'buffer_enabled'        => $data['buffer_enabled'] ?? false,
                    'max_patients'          => $data['max_patients'] ?? null,
                    'is_active'             => true,
                ]
            );

            $config->days()->delete();

            foreach ($data['days'] as $dayPayload) {
                $day = ScheduleDay::create([
                    'schedule_config_id' => $config->id,
                    'day_of_week'        => $dayPayload['day_of_week'],
                    'is_active'          => true,
                ]);

                foreach ($dayPayload['sessions'] as $sessionPayload) {
                    ScheduleSession::create([
                        'schedule_day_id' => $day->id,
                        'session_type'    => $sessionPayload['session_type'],
                        'start_time'      => $sessionPayload['start_time'],
                        'end_time'        => $sessionPayload['end_time'],
                        'is_active'       => true,
                    ]);
                }
            }

            return $config->fresh('days.sessions');
        });
    }


    public function activateVacation(ScheduleConfig $config, $from, $to): array
    {
        if ($to->lessThan($from)) {
            throw new BusinessException('End date must be after start date.');
        }

        return $this->transaction(function () use ($config, $from, $to) {
            $config->update([
                'vacation_start_date' => $from->toDateString(),
                'vacation_end_date'   => $to->toDateString(),
            ]);

            $affectedAvailable = $this->blockAvailableSlotsInRange($config, $from, $to);
            $affectedBookings  = $this->countBookedSlotsInRange($config, $from, $to);

            return [
                'config'                    => $config->fresh(),
                'affected_available_slots' => $affectedAvailable,
                'affected_bookings'         => $affectedBookings,
            ];
        });
    }


    public function deactivateVacation(ScheduleConfig $config): ScheduleConfig
    {
        $config->update([
            'vacation_start_date' => null,
            'vacation_end_date'   => null,
        ]);

        return $config->fresh();
    }


    private function assertNoScheduleConflict(Doctor $doctor, Clinic $clinic, array $days): void
    {
        $otherConfigs = ScheduleConfig::where('doctor_id', $doctor->id)
            ->where('clinic_id', '!=', $clinic->id)
            ->with('days.sessions')
            ->get();

        if ($otherConfigs->isEmpty()) {
            return;
        }

        foreach ($days as $dayPayload) {
            $dayOfWeek = $dayPayload['day_of_week'];

            foreach ($dayPayload['sessions'] as $sessionPayload) {
                $newStart = Carbon::parse($sessionPayload['start_time']);
                $newEnd   = Carbon::parse($sessionPayload['end_time']);

                foreach ($otherConfigs as $otherConfig) {
                    foreach ($otherConfig->days as $otherDay) {
                        if ($otherDay->day_of_week !== $dayOfWeek || ! $otherDay->is_active) {
                            continue;
                        }

                        foreach ($otherDay->sessions as $otherSession) {
                            if (! $otherSession->is_active) {
                                continue;
                            }

                            $existingStart = Carbon::parse($otherSession->start_time);
                            $existingEnd   = Carbon::parse($otherSession->end_time);

                            $overlaps = $newStart->lt($existingEnd) && $newEnd->gt($existingStart);

                            if ($overlaps) {
                                throw new BusinessException(sprintf(
                                    'Schedule conflict: doctor already has a session at another clinic (clinic_id: %d) on day %s from %s to %s.',
                                    $otherConfig->clinic_id,
                                    $dayOfWeek,
                                    $existingStart->format('H:i'),
                                    $existingEnd->format('H:i')
                                ));
                            }
                        }
                    }
                }
            }
        }
    }

    private function blockAvailableSlotsInRange(ScheduleConfig $config,  $from,  $to): int
    {
        return DoctorTimeSlot::where('doctor_id', $config->doctor_id)
            ->where('clinic_id', $config->clinic_id)
            ->where('status', SlotStatus::Available->value)
            ->whereBetween('starts_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->update(['status' => SlotStatus::Blocked->value]);
    }

    private function countBookedSlotsInRange(ScheduleConfig $config,  $from,  $to): int
    {
        return DoctorTimeSlot::where('doctor_id', $config->doctor_id)
            ->where('clinic_id', $config->clinic_id)
            ->where('status', SlotStatus::Booked->value)
            ->whereBetween('starts_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->count();
    }
}
