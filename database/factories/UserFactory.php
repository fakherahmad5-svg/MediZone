<?php

namespace Database\Factories;

use App\Core\Enums\Gender;
use App\Core\Enums\UserStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'phone' => fake()->unique()->e164PhoneNumber(),
            'dob' => fake()->date(),
            'gender' => fake()->randomElement(Gender::values()),
            'status' => UserStatus::Active->value,
            'remember_token' => Str::random(10),
        ];
    }
}
