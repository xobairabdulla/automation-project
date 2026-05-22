<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\FacebookPage;
use App\Models\Message;

class ConversationService
{
    public function findOrCreateCustomer(FacebookPage $page, string $senderId, ?string $name = null): Customer
    {
        return Customer::firstOrCreate(
            [
                'facebook_page_id' => $page->id,
                'external_customer_id' => $senderId,
            ],
            [
                'tenant_id' => $page->user_id,
                'name' => $name,
            ]
        );
    }

    public function findOrCreateConversation(FacebookPage $page, Customer $customer): Conversation
    {
        return Conversation::firstOrCreate(
            [
                'facebook_page_id' => $page->id,
                'customer_id' => $customer->id,
            ],
            [
                'tenant_id' => $page->user_id,
                'channel' => 'facebook_messenger',
                'status' => 'open',
            ]
        );
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function saveIncomingMessage(
        Conversation $conversation,
        string $messageText,
        string $externalMessageId,
        array $metadata = []
    ): Message {
        $message = Message::create([
            'tenant_id' => $conversation->tenant_id,
            'conversation_id' => $conversation->id,
            'facebook_page_id' => $conversation->facebook_page_id,
            'customer_id' => $conversation->customer_id,
            'direction' => 'incoming',
            'sender_type' => 'customer',
            'message_text' => $messageText,
            'external_message_id' => $externalMessageId,
            'status' => 'received',
            'metadata_json' => $metadata ?: null,
        ]);

        $conversation->update(['last_customer_message_at' => now(), 'is_read' => false]);

        return $message;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function saveOutgoingMessage(
        Conversation $conversation,
        string $replyText,
        string $status,
        ?string $externalMessageId = null,
        array $metadata = []
    ): Message {
        $message = Message::create([
            'tenant_id' => $conversation->tenant_id,
            'conversation_id' => $conversation->id,
            'facebook_page_id' => $conversation->facebook_page_id,
            'customer_id' => $conversation->customer_id,
            'direction' => 'outgoing',
            'sender_type' => 'bot',
            'message_text' => $replyText,
            'external_message_id' => $externalMessageId,
            'status' => $status,
            'metadata_json' => $metadata ?: null,
        ]);

        if ($status === 'sent') {
            $conversation->update(['last_reply_at' => now()]);
        }

        return $message;
    }
}
