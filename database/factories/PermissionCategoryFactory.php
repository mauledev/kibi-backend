<?php

namespace Database\Factories;

use App\Models\PermissionCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PermissionCategory>
 */
class PermissionCategoryFactory extends Factory
{
    protected $model = PermissionCategory::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'scope' => fake()->randomElement(['school', 'tenant', 'staff']),
            // Use a unique suffix to avoid violating the (scope, name) unique index
            // across multiple factory calls in the same test.
            'name' => fake()->unique()->word().'_'.fake()->numerify('###'),
        ];
    }

    /** School-scoped category. */
    public function school(): static
    {
        return $this->state(fn () => ['scope' => 'school']);
    }

    /**
     * Alias for school() — kept for backward compatibility with existing tests.
     * Previously meant "category without school_id"; now defaults to school scope.
     */
    public function system(): static
    {
        return $this->state(fn () => ['scope' => 'school']);
    }

    /** Staff-scoped category. */
    public function staff(): static
    {
        return $this->state(fn () => ['scope' => 'staff']);
    }

    /** Tenant-scoped category. */
    public function tenant(): static
    {
        return $this->state(fn () => ['scope' => 'tenant']);
    }
}
