<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\FacebookPage;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class FacebookMessageService
{
    public function __construct(
        private readonly FacebookService $facebookService,
        private readonly ConversationService $conversationService
    ) {}

    public function sendAutoReply(
        FacebookPage $page,
        Conversation $conversation,
        string $recipientId,
        string $replyText
    ): bool {
        try {
            $response = $this->facebookService->sendMessage(
                $page->page_access_token_encrypted,
                $recipientId,
                $replyText
            );

            if ($response->failed()) {
                $error = $response->json('error.message', 'Unknown error');
                Log::error('FacebookMessageService: send failed', [
                    'conversation_id' => $conversation->id,
                    'error' => $error,
                ]);

                $this->conversationService->saveOutgoingMessage(
                    $conversation,
                    $replyText,
                    'failed',
                    null,
                    ['error' => $error]
                );

                return false;
            }

            $externalMessageId = $response->json('message_id');

            $this->conversationService->saveOutgoingMessage(
                $conversation,
                $replyText,
                'sent',
                $externalMessageId
            );

            return true;
        } catch (\Throwable $e) {
            Log::error('FacebookMessageService: exception', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);

            $this->conversationService->saveOutgoingMessage(
                $conversation,
                $replyText,
                'failed',
                null,
                ['error' => $e->getMessage()]
            );

            return false;
        }
    }

    public function sendManualReply(
        FacebookPage $page,
        Conversation $conversation,
        string $recipientId,
        string $replyText,
        User $sender,
    ): bool {
        try {
            $response = $this->facebookService->sendMessage(
                $page->page_access_token_encrypted,
                $recipientId,
                $replyText
            );

            if ($response->failed()) {
                $error = $response->json('error.message', 'Unknown error');
                Log::error('FacebookMessageService: manual reply failed', [
                    'conversation_id' => $conversation->id,
                    'error' => $error,
                ]);

                $this->conversationService->saveOutgoingMessage(
                    $conversation,
                    $replyText,
                    'failed',
                    null,
                    ['error' => $error, 'sent_by' => $sender->id]
                );

                return false;
            }

            $externalMessageId = $response->json('message_id');

            $msg = $this->conversationService->saveOutgoingMessage(
                $conversation,
                $replyText,
                'sent',
                $externalMessageId,
                ['sent_by' => $sender->id]
            );

            $msg->update(['sender_type' => 'agent']);

            return true;
        } catch (\Throwable $e) {
            Log::error('FacebookMessageService: manual reply exception', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);

            $this->conversationService->saveOutgoingMessage(
                $conversation,
                $replyText,
                'failed',
                null,
                ['error' => $e->getMessage(), 'sent_by' => $sender->id]
            );

            return false;
        }
    }
}
