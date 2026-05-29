<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'is_staff' => false,
            'email' => fake()->unique()->safeEmail(),
            'password_hash' => Hash::make('password'),
            'first_name' => fake()->firstName(),
            'last_name_paternal' => fake()->lastName(),
            'last_name_maternal' => fake()->optional(0.8)->lastName(),
            'phone' => fake()->optional()->phoneNumber(),
            'status' => 'active',
        ];
    }

    public function staff(): static
    {
        return $this->state(fn () => ['is_staff' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => 'inactive']);
    }
}
