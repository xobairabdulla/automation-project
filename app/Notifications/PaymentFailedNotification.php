<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PaymentFailedNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly string $reason = '') {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'payment_failed',
            'title' => 'Payment Failed',
            'message' => 'Your payment could not be processed. Please update your payment method.',
            'reason' => $this->reason,
        ];
    }
}
