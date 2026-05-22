<?php

namespace Database\Factories;

use App\Models\Subscription;
use App\Models\UsageLimit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UsageLimit>
 */
class UsageLimitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'subscription_id' => Subscription::factory(),
            'message_reply_limit' => 100,
            'message_reply_used' => 0,
            'comment_reply_limit' => 100,
            'comment_reply_used' => 0,
            'ai_reply_limit' => 50,
            'ai_reply_used' => 0,
            'connected_page_limit' => 1,
            'team_member_limit' => 1,
            'knowledge_base_limit' => 10,
            'reset_at' => now()->addMonth(),
        ];
    }
}
