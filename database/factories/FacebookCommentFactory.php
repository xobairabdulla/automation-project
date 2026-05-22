<?php

namespace Database\Factories;

use App\Models\FacebookComment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FacebookComment>
 */
class FacebookCommentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $post = \App\Models\FacebookPost::factory()->create();

        return [
            'tenant_id' => $post->tenant_id,
            'facebook_page_id' => $post->facebook_page_id,
            'facebook_post_id' => $post->id,
            'external_comment_id' => fake()->numerify('##########'),
            'commenter_id' => fake()->numerify('##########'),
            'commenter_name' => fake()->name(),
            'comment_text' => fake()->sentence(),
            'status' => 'new',
            'created_time' => now(),
        ];
    }
}
