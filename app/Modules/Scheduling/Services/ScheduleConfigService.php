<?php

namespace App\Modules\Scheduling\Services;

use App\Models\Doctor;
use App\Models\ScheduleConfig;
use Illuminate\Support\Facades\Auth;

class ScheduleConfigService
{
    public function store(array $data): ScheduleConfig
    {
        $doctor = Doctor::where('user_id', Auth::id())->firstOrFail();
        $exists = ScheduleConfig::where('doctor_id', $doctor->id)->where('clinic_id', $data['clinic_id'])->exists();
        if ($exists) {
            throw new \Exception('Schedule config already exists for this clinic.', 409);
        }
        return ScheduleConfig::create([
            'doctor_id'             => $doctor->id,
            'clinic_id'             => $data['clinic_id'],
            'consultation_duration' => $data['consultation_duration'],
            'break_duration'        => $data['break_duration'] ?? 0,
            'max_patients'          => $data['max_patients'] ?? null,
            'buffer_enabled'        => $data['buffer_enabled'] ?? false,
            'is_vacation_mode'      => false,
            'is_active'             => true,
        ]);
    }

    public function getForDoctor()
    {
        $doctor = Doctor::where('user_id', Auth::id())->firstOrFail();
        return ScheduleConfig::where('doctor_id', $doctor->id)->with(['clinic'])->get();
    }

    public function update(ScheduleConfig $config, array $data): ScheduleConfig
    {
        $config->update($data);
        return $config->fresh();
    }

    public function toggleVacation(ScheduleConfig $config): ScheduleConfig
    {
        $config->update(['is_vacation_mode' => !$config->is_vacation_mode]);
        return $config->fresh();
    }
}
