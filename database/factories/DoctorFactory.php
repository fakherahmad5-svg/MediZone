<?php

namespace Database\Factories;

use App\Core\Enums\DoctorVerificationStatus;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DoctorFactory extends Factory
{
    protected $model = Doctor::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'practice_start_date' => $this->faker->dateTimeBetween('-20 years', '-1 year'),
            'verification_status' => DoctorVerificationStatus::Verified,
            'stripe_connect_id' => null,
            'stripe_active' => false,
            'commission_percentage' => $this->faker->randomElement(['10.00', '15.00', '20.00']),
            // NOTE: 'examination_fee' deliberately omitted — your pasted
            // "conceptual" doctors migration doesn't list this column, and
            // an earlier full migration paste in this conversation did.
            // I don't want to guess and risk an "Unknown column" SQL error.
            // If your real doctors table DOES have examination_fee (NOT NULL
            // or otherwise required), add it back here with a default like
            // '100.00'. It isn't needed for the cancellation test itself.
        ];
    }
}