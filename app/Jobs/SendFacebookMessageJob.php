<?php

namespace App\Jobs;

use App\Models\Conversation;
use App\Models\FacebookPage;
use App\Services\FacebookMessageService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendFacebookMessageJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly FacebookPage $page,
        public readonly Conversation $conversation,
        public readonly string $recipientId,
        public readonly string $replyText
    ) {}

    public function handle(FacebookMessageService $messageService): void
    {
        $messageService->sendAutoReply($this->page, $this->conversation, $this->recipientId, $this->replyText);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('SendFacebookMessageJob failed', [
            'conversation_id' => $this->conversation->id,
            'recipient_id' => $this->recipientId,
            'error' => $e->getMessage(),
        ]);
    }
}
