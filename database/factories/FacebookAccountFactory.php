<?php

namespace Database\Factories;

use App\Models\FacebookAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FacebookAccount>
 */
class FacebookAccountFactory extends Factory
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
            'facebook_user_id' => fake()->numerify('##########'),
            'name' => fake()->name(),
            'access_token_encrypted' => fake()->sha256(),
        ];
    }
}
