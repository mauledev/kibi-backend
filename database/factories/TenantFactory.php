<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'uuid' => (string) Str::uuid(),
            'owner_id' => User::factory(),
            'name' => $name,
            'slug' => fake()->unique()->slug(2),
            'legal_name' => $name.' S.A. de C.V.',
            'rfc' => strtoupper(fake()->bothify('???######???')),
            'contact_email' => fake()->companyEmail(),
            'contact_phone' => fake()->phoneNumber(),
            'status' => 'active',
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Tenant $tenant) {
            // Mirror the real tenant creation flow:
            // 1. Set tenant_id on the owner user
            User::where('id', $tenant->owner_id)->update(['tenant_id' => $tenant->id]);

            // 2. Assign the owner role so Gate::before hasRole('owner') works
            $ownerRole = Role::firstOrCreate(
                ['slug' => 'owner', 'tenant_id' => null],
                ['name' => 'Owner', 'hierarchy_level' => 2, 'is_system_role' => false],
            );

            UserRoleAssignment::create([
                'user_id' => $tenant->owner_id,
                'role_id' => $ownerRole->id,
                'school_id' => null,
                'assigned_by' => null,
                'assigned_at' => now(),
            ]);
        });
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => 'suspended']);
    }
}
