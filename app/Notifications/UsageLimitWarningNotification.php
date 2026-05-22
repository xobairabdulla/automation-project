<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class UsageLimitWarningNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $limitType,
        public readonly int $used,
        public readonly int $limit
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $pct = $this->limit > 0 ? round($this->used / $this->limit * 100) : 0;

        return [
            'type' => 'usage_limit_warning',
            'title' => 'Usage Limit Warning',
            'message' => "You have used {$pct}% of your {$this->limitType} limit ({$this->used}/{$this->limit}).",
            'limit_type' => $this->limitType,
            'used' => $this->used,
            'limit' => $this->limit,
        ];
    }
}
