<?php

namespace Database\Factories;

use App\Models\FacebookPost;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FacebookPost>
 */
class FacebookPostFactory extends Factory
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
            'external_post_id' => fake()->numerify('##########_##########'),
            'message' => fake()->sentence(),
            'permalink_url' => fake()->url(),
            'created_time' => now(),
        ];
    }
}
