<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'subscription_id' => null,
            'amount' => fake()->randomFloat(2, 9, 99),
            'currency' => 'USD',
            'status' => 'completed',
            'type' => 'subscription',
            'stripe_session_id' => null,
            'stripe_payment_intent_id' => null,
            'metadata_json' => null,
            'paid_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => 'pending', 'paid_at' => null]);
    }

    public function adminGranted(): static
    {
        return $this->state(['status' => 'completed', 'type' => 'admin_granted', 'amount' => 0]);
    }
}
