<?php

namespace Database\Factories;

use App\Models\School;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<School>
 */
class SchoolFactory extends Factory
{
    protected $model = School::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'tenant_id' => Tenant::factory(),
            'name' => fake()->company().' School',
            'slug' => fake()->unique()->slug(2),
            'address' => [
                'street' => fake()->streetName(),
                'exterior_number' => (string) fake()->buildingNumber(),
                'interior_number' => null,
                'neighborhood' => fake()->word(),
                'municipality' => fake()->city(),
                'state' => fake()->state(),
                'postal_code' => fake()->postcode(),
                'country' => 'MX',
            ],
            'phone' => fake()->phoneNumber(),
            'status' => 'active',
        ];
    }

    /** Scopes the school to an existing tenant. */
    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn () => ['tenant_id' => $tenant->id]);
    }

    /** Sets status to suspended. */
    public function suspended(): static
    {
        return $this->state(fn () => ['status' => 'suspended']);
    }

    /** Marks the school as soft-deleted (deactivated). */
    public function deactivated(): static
    {
        return $this->state(fn () => ['deleted_at' => now()]);
    }
}
