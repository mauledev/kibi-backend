<?php

namespace Database\Factories;

use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<StudentProfile>
 */
class StudentProfileFactory extends Factory
{
    protected $model = StudentProfile::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'user_id' => User::factory(),
            'birth_date' => fake()->optional(0.7)->dateTimeBetween('-20 years', '-5 years')?->format('Y-m-d'),
            'national_id' => fake()->optional(0.5)->bothify('??######??##??'),
            'enrollment_number' => fake()->optional(0.8)->numerify('ENR-####'),
            'gender' => fake()->optional(0.7)->randomElement(['male', 'female', 'other']),
            'blood_type' => fake()->optional(0.5)->randomElement(['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-']),
            'group_id' => null,
        ];
    }

    /** Link the profile to a specific user. */
    public function forUser(User $user): static
    {
        return $this->state(fn () => ['user_id' => $user->id]);
    }

    /** Set a specific group. */
    public function forGroup(int $groupId): static
    {
        return $this->state(fn () => ['group_id' => $groupId]);
    }
}
