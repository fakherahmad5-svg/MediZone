<?php

namespace Database\Factories;

use App\Core\Enums\ClinicStatus;
use App\Models\Clinic;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClinicFactory extends Factory
{
    protected $model = Clinic::class;

    public function definition(): array
    {
        return [
            // owner_id intentionally omitted — nullable per migration.
            'name' => $this->faker->company() . ' Clinic',
            'phone' => $this->faker->phoneNumber(),
            'address' => $this->faker->address(),
            'status' => ClinicStatus::Active,
            'latitude' => $this->faker->latitude(),
            'longitude' => $this->faker->longitude(),
        ];
    }
}