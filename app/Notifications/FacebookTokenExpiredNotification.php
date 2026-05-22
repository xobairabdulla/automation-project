<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class FacebookTokenExpiredNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly string $pageName) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'facebook_token_expired',
            'title' => 'Facebook Token Expired',
            'message' => "The access token for your Facebook Page \"{$this->pageName}\" has expired. Please reconnect your page.",
            'page_name' => $this->pageName,
        ];
    }
}
