<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Permission;
use App\Models\PermissionCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Permission>
 */
class PermissionFactory extends Factory
{
    protected $model = Permission::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $verb = fake()->randomElement(['create', 'update', 'delete', 'view', 'approve', 'publish']);
        $model = fake()->randomElement(['grade', 'payment', 'role', 'user', 'report', 'schedule']);

        return [
            'public_id' => (string) Str::uuid(),
            'category_id' => PermissionCategory::factory(),
            'name' => ucfirst($model).' '.ucfirst($verb),
            'slug' => $model.'.'.$verb,
        ];
    }

    /** Create a permission with a specific slug. */
    public function withSlug(string $slug): static
    {
        [$model, $verb] = explode('.', $slug) + ['unknown', 'unknown'];

        return $this->state(fn () => [
            'name' => ucfirst($model).' '.ucfirst($verb),
            'slug' => $slug,
        ]);
    }
}
