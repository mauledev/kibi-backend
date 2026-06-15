<?php

namespace Database\Factories;

use App\Models\TutorProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TutorProfile>
 */
class TutorProfileFactory extends Factory
{
    protected $model = TutorProfile::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'user_id' => User::factory(),
            'occupation' => fake()->optional(0.7)->jobTitle(),
        ];
    }

    /** Link the profile to a specific user. */
    public function forUser(User $user): static
    {
        return $this->state(fn () => ['user_id' => $user->id]);
    }

    /** Set a specific occupation. */
    public function withOccupation(string $occupation): static
    {
        return $this->state(fn () => ['occupation' => $occupation]);
    }
}
