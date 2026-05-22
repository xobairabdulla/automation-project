<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class UsageLimitExceededNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly string $limitType) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'usage_limit_exceeded',
            'title' => 'Usage Limit Exceeded',
            'message' => "Your {$this->limitType} limit has been reached. Upgrade your plan or extend your limit to continue.",
            'limit_type' => $this->limitType,
        ];
    }
}
