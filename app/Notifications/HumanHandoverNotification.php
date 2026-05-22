<?php

namespace App\Notifications;

use App\Models\Conversation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class HumanHandoverNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Conversation $conversation,
        public readonly string $reason,
        public readonly string $triggeredBy,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'human_handover',
            'conversation_id' => $this->conversation->id,
            'facebook_page_id' => $this->conversation->facebook_page_id,
            'reason' => $this->reason,
            'triggered_by' => $this->triggeredBy,
        ];
    }
}
