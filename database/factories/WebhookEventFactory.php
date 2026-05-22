<?php

namespace Database\Factories;

use App\Models\WebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookEvent>
 */
class WebhookEventFactory extends Factory
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
            'event_type' => 'message',
            'external_event_id' => fake()->uuid(),
            'payload_json' => [],
            'status' => 'queued',
        ];
    }

    public function withMessagePayload(string $senderId, string $messageText, string $mid): static
    {
        return $this->state([
            'event_type' => 'message',
            'payload_json' => [
                'id' => 'PAGE_ID',
                'messaging' => [
                    [
                        'sender' => ['id' => $senderId],
                        'recipient' => ['id' => 'PAGE_ID'],
                        'timestamp' => now()->timestamp,
                        'message' => ['mid' => $mid, 'text' => $messageText],
                    ],
                ],
            ],
        ]);
    }
}
