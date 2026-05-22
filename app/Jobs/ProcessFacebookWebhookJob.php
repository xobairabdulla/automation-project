<?php

namespace App\Jobs;

use App\Models\WebhookEvent;
use App\Models\WebhookEventLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessFacebookWebhookJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public readonly WebhookEvent $webhookEvent) {}

    public function handle(): void
    {
        $this->webhookEvent->update([
            'status' => 'processing',
        ]);

        WebhookEventLog::create([
            'webhook_event_id' => $this->webhookEvent->id,
            'status' => 'processing',
            'message' => 'Job picked up for processing.',
        ]);

        $eventType = $this->webhookEvent->event_type;

        if ($eventType === 'message') {
            ProcessIncomingMessageJob::dispatch($this->webhookEvent);
            WebhookEventLog::create([
                'webhook_event_id' => $this->webhookEvent->id,
                'status' => 'completed',
                'message' => 'Dispatched to ProcessIncomingMessageJob.',
            ]);
        } elseif (str_starts_with($eventType, 'comment_')) {
            ProcessIncomingCommentJob::dispatch($this->webhookEvent);
            WebhookEventLog::create([
                'webhook_event_id' => $this->webhookEvent->id,
                'status' => 'completed',
                'message' => 'Dispatched to ProcessIncomingCommentJob.',
            ]);
        } else {
            $this->webhookEvent->update([
                'status' => 'completed',
                'processed_at' => now(),
            ]);

            WebhookEventLog::create([
                'webhook_event_id' => $this->webhookEvent->id,
                'status' => 'completed',
                'message' => "Event type '{$eventType}' not yet handled.",
            ]);
        }
    }

    public function failed(\Throwable $e): void
    {
        $this->webhookEvent->update([
            'status' => 'failed',
            'error_message' => $e->getMessage(),
        ]);

        WebhookEventLog::create([
            'webhook_event_id' => $this->webhookEvent->id,
            'status' => 'failed',
            'message' => $e->getMessage(),
        ]);
    }
}
