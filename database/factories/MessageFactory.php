<?php

namespace Database\Factories;

use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => \App\Models\User::factory(),
            'conversation_id' => \App\Models\Conversation::factory(),
            'facebook_page_id' => \App\Models\FacebookPage::factory(),
            'customer_id' => \App\Models\Customer::factory(),
            'direction' => 'incoming',
            'sender_type' => 'customer',
            'message_text' => fake()->sentence(),
            'status' => 'received',
        ];
    }

    public function outgoing(): static
    {
        return $this->state(['direction' => 'outgoing', 'sender_type' => 'bot', 'status' => 'sent']);
    }

    public function failed(): static
    {
        return $this->state(['direction' => 'outgoing', 'sender_type' => 'bot', 'status' => 'failed']);
    }
}
