<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'monthly_price' => fake()->randomFloat(2, 0, 199),
            'yearly_price' => fake()->randomFloat(2, 0, 1999),
            'message_reply_limit' => fake()->numberBetween(100, 1000),
            'comment_reply_limit' => fake()->numberBetween(100, 1000),
            'ai_reply_limit' => fake()->numberBetween(50, 500),
            'connected_page_limit' => fake()->numberBetween(1, 10),
            'team_member_limit' => fake()->numberBetween(1, 10),
            'knowledge_base_limit' => fake()->numberBetween(10, 100),
            'features_json' => ['automation', 'analytics'],
            'status' => 'active',
        ];
    }
}
