<?php

namespace Database\Factories;

use App\Core\Enums\SlotStatus;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorTimeSlot;
use Illuminate\Database\Eloquent\Factories\Factory;

class DoctorTimeSlotFactory extends Factory
{
    protected $model = DoctorTimeSlot::class;

    public function definition(): array
    {
        // Default: safely more than 24h out, so a plain
        // DoctorTimeSlot::factory()->create() never accidentally lands
        // in the <=24h tier unless a test explicitly asks for that.
        $startsAt = now()->addDays(3);

        return [
            'clinic_id' => Clinic::factory(),
            'doctor_id' => Doctor::factory(),
            'session_id' => null,
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->addHour(),
            'status' => SlotStatus::Available,
        ];
    }
}