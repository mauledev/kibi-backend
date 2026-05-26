<?php

namespace Database\Factories;

use App\Models\Tenant;
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
            'public_id' => (string) Str::uuid(),
            'tenant_id' => Tenant::factory(),
            'email' => fake()->unique()->safeEmail(),
            'password_hash' => Hash::make('password'),
            'full_name' => fake()->name(),
            'phone' => fake()->optional()->phoneNumber(),
            'status' => 'active',
        ];
    }

    public function staff(): static
    {
        return $this->state(fn () => ['tenant_id' => null]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => 'inactive']);
    }
}
