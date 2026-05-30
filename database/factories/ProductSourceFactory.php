<?php

namespace Database\Factories;

use App\Models\ProductSource;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductSource>
 */
class ProductSourceFactory extends Factory
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
            'name' => fake()->words(3, true),
            'source_type' => fake()->randomElement(['woocommerce_api', 'shopify_api', 'custom_api', 'manual']),
            'website_url' => null,
            'api_url' => null,
            'api_key' => null,
            'api_secret' => null,
            'is_active' => true,
            'product_image_reply_enabled' => true,
            'max_images_per_reply' => 4,
        ];
    }
}
