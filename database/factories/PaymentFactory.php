<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\School;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'tenant_id' => Tenant::factory(),
            'school_id' => School::factory(),
            'created_by' => null,
            'status' => 'pending',
            'payer_name' => fake()->name(),
            'reference' => strtoupper(fake()->bothify('REF-####-????')),
            'amount_cents' => fake()->numberBetween(50_000, 500_000),
            'received_amount_cents' => null,
            'currency' => 'MXN',
            'paid_at' => fake()->dateTimeBetween('-30 days', 'now'),
        ];
    }

    /** Scopes the payment to an existing tenant. */
    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn () => ['tenant_id' => $tenant->id]);
    }

    /** Records the user who uploaded the receipt. */
    public function createdBy(User $user): static
    {
        return $this->state(fn () => ['created_by' => $user->id]);
    }

    /** Scopes the payment to an existing school. */
    public function forSchool(School $school): static
    {
        return $this->state(fn () => ['school_id' => $school->id, 'tenant_id' => $school->tenant_id]);
    }

    /** Sets status to approved with a received_amount_cents value. */
    public function approved(?int $receivedAmountCents = null): static
    {
        return $this->state(fn (array $attrs) => [
            'status' => 'approved',
            'received_amount_cents' => $receivedAmountCents ?? $attrs['amount_cents'] ?? 100_000,
        ]);
    }

    /** Sets status to rejected. */
    public function rejected(): static
    {
        return $this->state(fn () => ['status' => 'rejected']);
    }

    /** Sets status to with_observation. */
    public function withObservation(): static
    {
        return $this->state(fn () => ['status' => 'with_observation']);
    }
}
