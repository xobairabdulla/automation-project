<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
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
            'external_customer_id' => fake()->numerify('##########'),
            'name' => fake()->name(),
        ];
    }
}
