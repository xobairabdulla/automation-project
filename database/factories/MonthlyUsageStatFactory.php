<?php

namespace Database\Factories;

use App\Models\MonthlyUsageStat;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MonthlyUsageStat>
 */
class MonthlyUsageStatFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => User::factory(),
            'year' => fake()->numberBetween(2024, 2026),
            'month' => fake()->numberBetween(1, 12),
            'messages_received' => fake()->numberBetween(0, 5000),
            'comments_received' => fake()->numberBetween(0, 2500),
            'message_replies_sent' => fake()->numberBetween(0, 4000),
            'comment_replies_sent' => fake()->numberBetween(0, 2000),
            'ai_replies_sent' => fake()->numberBetween(0, 3000),
            'manual_replies_sent' => fake()->numberBetween(0, 1500),
            'human_handovers' => fake()->numberBetween(0, 500),
            'failed_replies' => fake()->numberBetween(0, 200),
        ];
    }
}
