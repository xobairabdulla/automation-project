<?php

namespace Database\Factories;

use App\Models\Conversation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
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
            'facebook_page_id' => \App\Models\FacebookPage::factory(),
            'customer_id' => \App\Models\Customer::factory(),
            'channel' => 'facebook_messenger',
            'status' => 'open',
            'human_takeover' => false,
        ];
    }

    public function humanTakeover(): static
    {
        return $this->state(['human_takeover' => true]);
    }
}
