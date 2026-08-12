<?php

namespace App\Modules\Scheduling\Services;

use App\Core\Enums\SlotStatus;
use App\Core\Exceptions\NotFoundException;
use App\Core\Services\BaseService;
use App\Models\BlockedTime;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorTimeSlot;
use Illuminate\Database\Eloquent\Collection;

class BlockedTimeService extends BaseService
{
    public function listForDoctorClinic(Doctor $doctor, int $clinicId): Collection
    {
        return BlockedTime::where('doctor_id', $doctor->id)
            ->where('clinic_id', $clinicId)
            ->orderByDesc('id')
            ->get();
    }


    public function create(Doctor $doctor, Clinic $clinic, array $data): array
    {
        return $this->transaction(function () use ($doctor, $clinic, $data) {
            $blockedTime = BlockedTime::create([
                'doctor_id'   => $doctor->id,
                'clinic_id'   => $clinic->id,
                'block_date'  => $data['block_date'] ?? null,
                'day_of_week' => $data['day_of_week'] ?? null,
                'start_time'  => $data['start_time'],
                'end_time'    => $data['end_time'],
                'reason'      => $data['reason'] ?? null,
            ]);

            $affectedAvailable = $this->blockOverlappingAvailableSlots($doctor, $clinic, $blockedTime);
            $affectedBookings  = $this->countOverlappingBookedSlots($doctor, $clinic, $blockedTime);

            return [
                'blocked_time'              => $blockedTime,
                'affected_available_slots' => $affectedAvailable,
                'affected_bookings'         => $affectedBookings,
            ];
        });
    }

    public function delete(Doctor $doctor, BlockedTime $blockedTime): void
    {
        if ($blockedTime->doctor_id !== $doctor->id) {
            throw new NotFoundException('Blocked time not found.');
        }

        $blockedTime->delete();


    }

    // ─────────────────────────────────────────────────────────────

    private function blockOverlappingAvailableSlots(Doctor $doctor, Clinic $clinic, BlockedTime $blockedTime): int
    {
        $candidates = DoctorTimeSlot::where('doctor_id', $doctor->id)
            ->where('clinic_id', $clinic->id)
            ->where('status', SlotStatus::Available->value)
            ->get();

        $count = 0;

        foreach ($candidates as $slot) {
            $overlaps = $blockedTime->overlaps(
                $slot->starts_at,
                $slot->starts_at->format('H:i:s'),
                $slot->ends_at->format('H:i:s')
            );

            if ($overlaps) {
                $slot->update(['status' => SlotStatus::Blocked->value]);
                $count++;
            }
        }

        return $count;
    }

    private function countOverlappingBookedSlots(Doctor $doctor, Clinic $clinic, BlockedTime $blockedTime): int
    {
        return DoctorTimeSlot::where('doctor_id', $doctor->id)
            ->where('clinic_id', $clinic->id)
            ->where('status', SlotStatus::Booked->value)
            ->get()
            ->filter(fn (DoctorTimeSlot $slot) => $blockedTime->overlaps(
                $slot->starts_at,
                $slot->starts_at->format('H:i:s'),
                $slot->ends_at->format('H:i:s')
            ))
            ->count();
    }
}
