<?php

namespace Database\Factories;

use App\Models\AiReplyLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiReplyLog>
 */
class AiReplyLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $page = \App\Models\FacebookPage::factory()->create();

        return [
            'tenant_id' => $page->user_id,
            'facebook_page_id' => $page->id,
            'conversation_id' => null,
            'facebook_comment_id' => null,
            'prompt' => fake()->sentence(),
            'response' => fake()->paragraph(),
            'model' => fake()->randomElement(['gpt-4', 'gpt-3.5-turbo', 'claude-3-opus']),
            'tokens_used' => fake()->numberBetween(50, 1000),
            'status' => 'success',
            'error_message' => null,
        ];
    }
}
