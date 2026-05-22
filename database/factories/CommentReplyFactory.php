<?php

namespace Database\Factories;

use App\Models\CommentReply;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommentReply>
 */
class CommentReplyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $comment = \App\Models\FacebookComment::factory()->create();

        return [
            'tenant_id' => $comment->tenant_id,
            'facebook_comment_id' => $comment->id,
            'reply_text' => fake()->sentence(),
            'external_reply_id' => fake()->numerify('##########'),
            'status' => 'sent',
            'error_message' => null,
        ];
    }
}
