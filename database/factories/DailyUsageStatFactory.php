<?php

namespace Database\Factories;

use App\Models\DailyUsageStat;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyUsageStat>
 */
class DailyUsageStatFactory extends Factory
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
            'date' => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'messages_received' => fake()->numberBetween(0, 200),
            'comments_received' => fake()->numberBetween(0, 100),
            'message_replies_sent' => fake()->numberBetween(0, 150),
            'comment_replies_sent' => fake()->numberBetween(0, 80),
            'ai_replies_sent' => fake()->numberBetween(0, 100),
            'manual_replies_sent' => fake()->numberBetween(0, 50),
            'human_handovers' => fake()->numberBetween(0, 20),
            'failed_replies' => fake()->numberBetween(0, 10),
        ];
    }
}
