<?php

namespace Database\Factories;

use App\Models\FacebookPage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FacebookPage>
 */
class FacebookPageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => null,
            'user_id' => \App\Models\User::factory(),
            'facebook_account_id' => \App\Models\FacebookAccount::factory(),
            'page_id' => fake()->numerify('##########'),
            'page_name' => fake()->company(),
            'page_access_token_encrypted' => fake()->sha256(),
            'is_connected' => true,
            'automation_enabled' => true,
            'message_automation_enabled' => true,
            'comment_automation_enabled' => true,
        ];
    }

    public function automationDisabled(): static
    {
        return $this->state(['message_automation_enabled' => false]);
    }

    public function disconnected(): static
    {
        return $this->state(['is_connected' => false]);
    }
}
