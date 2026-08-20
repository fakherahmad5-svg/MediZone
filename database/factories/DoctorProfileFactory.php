<?php

namespace Database\Factories;

use App\Models\Doctor;
use App\Models\DoctorProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class DoctorProfileFactory extends Factory
{
    protected $model = DoctorProfile::class;

    public function definition(): array
    {
        return [
            'doctor_id' => Doctor::factory(),
            'biography' => fake()->paragraph(),
            'qualifications' => [],
            'consultation_fee' => '100.00',
            'languages' => [],
        ];
    }
}