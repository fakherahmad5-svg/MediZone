<?php

namespace App\Modules\Scheduling\Services;

use App\Core\Enums\SlotStatus;
use App\Core\Exceptions\ConflictException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Services\BaseService;
use App\Models\Doctor;
use App\Models\DoctorTimeSlot;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;


class DoctorTimeSlotService extends BaseService
{

    public function availability(Doctor $doctor, ?int $clinicId, Carbon $from, Carbon $to): Collection
    {
        return DoctorTimeSlot::where('doctor_id', $doctor->id)
            ->when($clinicId, fn ($q) => $q->where('clinic_id', $clinicId))
            ->where('status', SlotStatus::Available->value)
            ->whereBetween('starts_at', [$from, $to])
            ->orderBy('starts_at')
            ->get();
    }


    public function fullSchedule(Doctor $doctor, ?int $clinicId, Carbon $from, Carbon $to): Collection
    {
        return DoctorTimeSlot::where('doctor_id', $doctor->id)
            ->when($clinicId, fn ($q) => $q->where('clinic_id', $clinicId))
            ->whereBetween('starts_at', [$from, $to])
            ->orderBy('starts_at')
            ->get();
    }


    public function lockForBooking(int $slotId): DoctorTimeSlot
    {
        return DB::transaction(function () use ($slotId) {
            $slot = DoctorTimeSlot::where('id', $slotId)->lockForUpdate()->first();

            if (! $slot) {
                throw new NotFoundException('Time slot not found.');
            }

            if (! $slot->isAvailable()) {
                throw new ConflictException(
                    'This time slot is no longer available. Please choose another slot.'
                );
            }

            $slot->update(['status' => SlotStatus::Booked->value]);

            return $slot->fresh();
        });
    }


    public function release(int $slotId): void
    {
        DB::transaction(function () use ($slotId) {
            $slot = DoctorTimeSlot::where('id', $slotId)->lockForUpdate()->first();

            if ($slot && $slot->status === SlotStatus::Booked) {
                $slot->update(['status' => SlotStatus::Available->value]);
            }
        });
    }
}
