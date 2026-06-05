<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\PaymentStateTransition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentStateTransition>
 */
class PaymentStateTransitionFactory extends Factory
{
    protected $model = PaymentStateTransition::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory(),
            'event' => 'created',
            'from_status' => null,
            'to_status' => 'pending',
            'actor_user_id' => null,
            'actor_name' => 'System',
            'reason' => null,
            'note' => null,
            'created_at' => now(),
        ];
    }

    public function forPayment(Payment $payment): static
    {
        return $this->state(fn () => ['payment_id' => $payment->id]);
    }

    public function approved(?string $note = null): static
    {
        return $this->state(fn () => [
            'event' => 'approved',
            'from_status' => 'pending',
            'to_status' => 'approved',
            'reason' => null,
            'note' => $note,
        ]);
    }

    public function rejected(string $reason = 'amount_mismatch', ?string $note = null): static
    {
        return $this->state(fn () => [
            'event' => 'rejected',
            'from_status' => 'pending',
            'to_status' => 'rejected',
            'reason' => $reason,
            'note' => $note,
        ]);
    }
}
