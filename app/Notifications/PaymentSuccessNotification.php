<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PaymentSuccessNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly Payment $payment) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'payment_success',
            'title' => 'Payment Successful',
            'message' => "Your payment of \${$this->payment->amount} was successful.",
            'payment_id' => $this->payment->id,
            'amount' => $this->payment->amount,
        ];
    }
}
