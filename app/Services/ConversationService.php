<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\FacebookPage;
use App\Models\Message;
use App\Services\Facebook\FacebookUserProfileService;
use Illuminate\Support\Facades\Log;

class ConversationService
{
    public function syncCustomerProfile(Customer $customer, FacebookPage $page, FacebookUserProfileService $profileService): void
    {
        if ($customer->profileSyncedRecently()) {
            Log::debug('ConversationService: Skipping profile sync — already recent', [
                'customer_id' => $customer->id,
                'psid' => $customer->external_customer_id,
            ]);

            return;
        }

        $token = $page->page_access_token_encrypted;

        if (! $token) {
            Log::warning('ConversationService: Page access token missing — cannot sync profile', [
                'page_id' => $page->id,
                'customer_id' => $customer->id,
            ]);

            return;
        }

        $profile = $profileService->fetchProfile($customer->external_customer_id, $token);

        if ($profile === null) {
            return;
        }

        $customer->update([
            'name' => $profile['full_name'] ?? $customer->name,
            'facebook_first_name' => $profile['first_name'],
            'facebook_last_name' => $profile['last_name'],
            'profile_picture_url' => $profile['profile_pic'] ?? $customer->profile_picture_url,
            'facebook_locale' => $profile['locale'],
            'facebook_timezone' => $profile['timezone'],
            'profile_synced_at' => now(),
        ]);
    }

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
     * @param  array<string, mixed>  $metadata
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
     * @param  array<string, mixed>  $metadata
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
