<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    protected $model = Role::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'tenant_id' => Tenant::factory(),
            'name' => fake()->jobTitle(),
            'slug' => fake()->unique()->slug(1),
            'hierarchy_level' => fake()->numberBetween(3, 9),
            'is_system_role' => false,
        ];
    }

    /** Tenant-scoped role (default). */
    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn () => ['tenant_id' => $tenant->id]);
    }

    /** System role (tenant_id IS NULL, is_system_role = true). */
    public function system(): static
    {
        return $this->state(fn () => [
            'tenant_id' => null,
            'is_system_role' => true,
        ]);
    }

    /** Owner role — level 2, tenant-scoped. */
    public function owner(): static
    {
        return $this->state(fn () => [
            'name' => 'Owner',
            'slug' => 'owner',
            'hierarchy_level' => 2,
            'is_system_role' => false,
        ]);
    }

    /** Role at a specific hierarchy level. */
    public function atLevel(int $level): static
    {
        return $this->state(fn () => ['hierarchy_level' => $level]);
    }
}
