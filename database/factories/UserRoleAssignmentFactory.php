<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserRoleAssignment>
 */
class UserRoleAssignmentFactory extends Factory
{
    protected $model = UserRoleAssignment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'role_id' => Role::factory(),
            'school_id' => null,
            'assigned_by' => null,
            'assigned_at' => now(),
            'revoked_at' => null,
        ];
    }

    /** Active assignment (revoked_at IS NULL). */
    public function active(): static
    {
        return $this->state(fn () => ['revoked_at' => null]);
    }

    /** Revoked assignment (revoked_at IS NOT NULL). */
    public function revoked(): static
    {
        return $this->state(fn () => ['revoked_at' => now()->subDay()]);
    }

    /** Assign to a specific user. */
    public function forUser(User $user): static
    {
        return $this->state(fn () => ['user_id' => $user->id]);
    }

    /** Assign a specific role. */
    public function forRole(Role $role): static
    {
        return $this->state(fn () => ['role_id' => $role->id]);
    }
}
