<?php

declare(strict_types=1);

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
            'public_id' => (string) Str::uuid(),
            'school_id' => null, // system category by default
            'name' => fake()->randomElement(['academic', 'financial', 'hr', 'communication', 'configuration']),
        ];
    }

    /** System-level category (school_id IS NULL). */
    public function system(): static
    {
        return $this->state(fn () => ['school_id' => null]);
    }
}
