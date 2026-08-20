<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PatientFactory extends Factory
{
    protected $model = Patient::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'blood_type' => $this->faker->randomElement(['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-']),
            'no_show_count' => 0,
            'cash_payment_blocked' => false,
        ];
    }

    public function withNoShows(int $count): static
    {
        return $this->state(fn () => [
            'no_show_count' => $count,
            'cash_payment_blocked' => $count >= 2,
        ]);
    }
}