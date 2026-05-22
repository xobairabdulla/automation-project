<?php

namespace Database\Factories;

use App\Models\LimitExtensionPackage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LimitExtensionPackage>
 */
class LimitExtensionPackageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'message_extra' => fake()->randomElement([250, 500, 1000]),
            'comment_extra' => fake()->randomElement([250, 500, 1000]),
            'ai_extra' => fake()->randomElement([100, 250, 500]),
            'price' => fake()->randomFloat(2, 4.99, 49.99),
            'currency' => 'USD',
            'stripe_price_id' => null,
            'is_active' => true,
        ];
    }
}
