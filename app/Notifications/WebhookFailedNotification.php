<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class WebhookFailedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $eventType,
        public readonly string $errorMessage
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'webhook_failed',
            'title' => 'Webhook Processing Failed',
            'message' => "A {$this->eventType} webhook event failed to process: {$this->errorMessage}",
            'event_type' => $this->eventType,
            'error' => $this->errorMessage,
        ];
    }
}
