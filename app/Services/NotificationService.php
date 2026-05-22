<?php

namespace App\Services;

use App\Models\EmailLog;
use App\Models\User;
use App\Notifications\FacebookTokenExpiredNotification;
use App\Notifications\PaymentFailedNotification;
use App\Notifications\PaymentSuccessNotification;
use App\Notifications\UsageLimitExceededNotification;
use App\Notifications\UsageLimitWarningNotification;
use App\Notifications\WebhookFailedNotification;
use Illuminate\Notifications\Notification;

class NotificationService
{
    public function send(User $user, Notification $notification): void
    {
        $user->notify($notification);
    }

    public function notifyUsageLimitWarning(User $user, string $limitType, int $used, int $limit): void
    {
        $user->notify(new UsageLimitWarningNotification($limitType, $used, $limit));
    }

    public function notifyUsageLimitExceeded(User $user, string $limitType): void
    {
        $user->notify(new UsageLimitExceededNotification($limitType));
    }

    public function notifyPaymentSuccess(\App\Models\Payment $payment): void
    {
        $payment->user?->notify(new PaymentSuccessNotification($payment));
    }

    public function notifyPaymentFailed(User $user, string $reason = ''): void
    {
        $user->notify(new PaymentFailedNotification($reason));
    }

    public function notifyWebhookFailed(User $user, string $eventType, string $error): void
    {
        $user->notify(new WebhookFailedNotification($eventType, $error));
    }

    public function notifyFacebookTokenExpired(User $user, string $pageName): void
    {
        $user->notify(new FacebookTokenExpiredNotification($pageName));
    }

    public function logEmail(
        string $toEmail,
        string $subject,
        string $status = 'sent',
        ?int $userId = null,
        ?int $tenantId = null,
        ?string $errorMessage = null
    ): EmailLog {
        return EmailLog::record($toEmail, $subject, $status, $userId, $tenantId, $errorMessage);
    }
}
