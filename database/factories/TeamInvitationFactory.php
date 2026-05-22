<?php

namespace Database\Factories;

use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TeamInvitation>
 */
class TeamInvitationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'email' => fake()->unique()->safeEmail(),
            'role' => fake()->randomElement(['agent', 'support-admin']),
            'token' => Str::random(64),
            'accepted_at' => null,
            'expires_at' => now()->addDays(7),
        ];
    }

    public function accepted(): static
    {
        return $this->state(['accepted_at' => now()]);
    }

    public function expired(): static
    {
        return $this->state(['expires_at' => now()->subDay()]);
    }
}
