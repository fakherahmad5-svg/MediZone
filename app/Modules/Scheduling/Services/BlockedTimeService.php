<?php

namespace App\Modules\Scheduling\Services;

use App\Models\BlockedTime;
use App\Models\Doctor;
use Illuminate\Support\Facades\Auth;

class BlockedTimeService
{
    public function store(array $data): BlockedTime
    {
        $doctor = Doctor::where('user_id', Auth::id())->firstOrFail();
        return BlockedTime::create([
            'doctor_id'  => $doctor->id,
            'clinic_id'  => $data['clinic_id'],
            'block_date' => $data['block_date'] ?? null,
            'day_of_week'=> $data['day_of_week'] ?? null,
            'start_time' => $data['start_time'],
            'end_time'   => $data['end_time'],
            'reason'     => $data['reason'] ?? null,
        ]);
    }

    public function getForDoctor()
    {
        $doctor = Doctor::where('user_id', Auth::id())->firstOrFail();
        return BlockedTime::where('doctor_id', $doctor->id)->orderBy('block_date')->get();
    }

    public function delete(BlockedTime $blockedTime): void
    {
        $blockedTime->delete();
    }
}
