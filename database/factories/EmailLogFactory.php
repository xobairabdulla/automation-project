<?php

namespace Database\Factories;

use App\Models\EmailLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailLog>
 */
class EmailLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => User::factory(),
            'user_id' => null,
            'to_email' => fake()->safeEmail(),
            'subject' => fake()->sentence(4),
            'status' => 'sent',
            'error_message' => null,
        ];
    }

    public function failed(): static
    {
        return $this->state([
            'status' => 'failed',
            'error_message' => 'SMTP connection refused',
        ]);
    }
}
