<?php

namespace App\Jobs;

use App\Models\AiPrompt;
use App\Models\Conversation;
use App\Models\FacebookPage;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Models\WebhookEventLog;
use App\Services\AIReplyService;
use App\Services\ConversationService;
use App\Services\Facebook\FacebookUserProfileService;
use App\Services\FacebookProductReplyService;
use App\Services\HumanHandoverService;
use App\Services\ProductMatcherService;
use App\Services\RuleEngineService;
use App\Services\UsageLimitService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessIncomingMessageJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public readonly WebhookEvent $webhookEvent) {}

    public function handle(ConversationService $conversationService, UsageLimitService $usageLimitService, RuleEngineService $ruleEngineService, AIReplyService $aiReplyService, HumanHandoverService $handoverService, ProductMatcherService $productMatcher, FacebookProductReplyService $productReplyService, FacebookUserProfileService $profileService): void
    {
        $payload = $this->webhookEvent->payload_json;
        $messaging = $payload['messaging'][0] ?? null;

        if (! $messaging || ! isset($messaging['message']['text'])) {
            $this->markIgnored('No text message in payload.');

            return;
        }

        $senderId = $messaging['sender']['id'] ?? null;
        $messageText = $messaging['message']['text'];
        $messageId = $messaging['message']['mid'] ?? null;

        if (! $senderId) {
            $this->markIgnored('No sender ID in payload.');

            return;
        }

        $page = FacebookPage::find($this->webhookEvent->facebook_page_id);

        if (! $page) {
            $this->markIgnored('Facebook page not found.');

            return;
        }

        $customer = $conversationService->findOrCreateCustomer($page, $senderId);
        $conversationService->syncCustomerProfile($customer, $page, $profileService);
        $conversation = $conversationService->findOrCreateConversation($page, $customer);
        $conversationService->saveIncomingMessage($conversation, $messageText, $messageId ?? '');

        if (! $page->message_automation_enabled) {
            Log::info('ProcessIncomingMessageJob: Stopped — message automation disabled', [
                'page_id' => $page->id,
                'webhook_event_id' => $this->webhookEvent->id,
            ]);
            $this->markCompleted('Message automation disabled for page.');

            return;
        }

        if ($conversation->human_takeover) {
            Log::info('ProcessIncomingMessageJob: Stopped — human takeover active', [
                'conversation_id' => $conversation->id,
            ]);
            $this->markCompleted('Human takeover active — no auto reply.');

            return;
        }

        $user = User::find($page->user_id);

        if (! $user) {
            Log::warning('ProcessIncomingMessageJob: Stopped — user not found', [
                'page_id' => $page->id,
            ]);
            $this->markFailed('User not found for page.');

            return;
        }

        if (! $usageLimitService->canSendMessageReply($user)) {
            Log::info('ProcessIncomingMessageJob: Stopped — message reply usage limit reached', [
                'user_id' => $user->id,
            ]);
            $this->markCompleted('Message reply usage limit reached.');

            return;
        }

        try {
            $usageLimitService->incrementUsage($user, 'message_reply', 1, [
                'conversation_id' => $conversation->id,
                'customer_id' => $customer->id,
            ]);
        } catch (\InvalidArgumentException $e) {
            Log::info('ProcessIncomingMessageJob: Stopped — usage limit exceeded mid-flight', [
                'error' => $e->getMessage(),
            ]);
            $this->markCompleted('Usage limit check failed: '.$e->getMessage());

            return;
        }

        $sensitiveTrigger = $handoverService->detectSensitiveTrigger($messageText);
        if ($sensitiveTrigger) {
            $handoverService->triggerHandover($conversation, $sensitiveTrigger);
            $this->markCompleted("Sensitive trigger detected: {$sensitiveTrigger} — human handover triggered.");

            return;
        }

        // Product image inquiry — check before rule engine so product replies take priority
        if ($productMatcher->hasProductImageIntent($messageText) || $productMatcher->hasMoreImagesIntent($messageText)) {
            $handled = $productReplyService->handleMessengerInquiry($page, $conversation, $senderId, $messageText);
            if ($handled) {
                $this->markCompleted('Product image inquiry handled.');

                return;
            }
        }

        $action = $ruleEngineService->findMatchingAction($page, 'message', $messageText);

        if ($action && $action->action_type === 'do_nothing') {
            $this->markCompleted('Rule matched: do_nothing action.');

            return;
        }

        if ($action && $action->action_type === 'human_handover') {
            $conversation->update(['human_takeover' => true]);
            $this->markCompleted('Rule matched: human_handover triggered.');

            return;
        }

        $replyText = $action?->resolveReplyText();

        if ($replyText === null) {
            $replyText = $this->tryAIReply($page, $user, $conversation->id, $messageText, $usageLimitService, $aiReplyService, $conversation, $handoverService);

            if ($replyText === null && $conversation->fresh()->human_takeover) {
                $this->markCompleted('AI unknown answer — human takeover triggered.');

                return;
            }
        }

        $usingFallback = false;
        if ($replyText === null) {
            $replyText = $this->defaultReply();
            $usingFallback = true;
            Log::info('ProcessIncomingMessageJob: Using fallback reply', [
                'webhook_event_id' => $this->webhookEvent->id,
                'message' => mb_substr($messageText, 0, 100),
            ]);
        }

        Log::info('ProcessIncomingMessageJob: Dispatching reply', [
            'webhook_event_id' => $this->webhookEvent->id,
            'message' => mb_substr($messageText, 0, 100),
            'reply' => mb_substr($replyText, 0, 100),
            'fallback' => $usingFallback,
        ]);

        SendFacebookMessageJob::dispatch($page, $conversation, $senderId, $replyText);

        $this->markCompleted('Incoming message processed, reply dispatched.');
    }

    public function failed(\Throwable $e): void
    {
        Log::error('ProcessIncomingMessageJob failed', [
            'webhook_event_id' => $this->webhookEvent->id,
            'error' => $e->getMessage(),
        ]);

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

    private function tryAIReply(
        FacebookPage $page,
        User $user,
        int $conversationId,
        string $messageText,
        UsageLimitService $usageLimitService,
        AIReplyService $aiReplyService,
        Conversation $conversation,
        HumanHandoverService $handoverService,
    ): ?string {
        if (! $usageLimitService->canGenerateAIReply($user)) {
            return null;
        }

        $reply = $aiReplyService->generateReply($page, $messageText, $conversationId);

        if ($reply === null) {
            $aiPrompt = AiPrompt::where('facebook_page_id', $page->id)->first();
            if (($aiPrompt?->unknown_answer_action ?? 'send_default') === 'human_handover') {
                $handoverService->triggerHandover($conversation, 'ai_unknown_answer');
            }

            return null;
        }

        try {
            $usageLimitService->incrementUsage($user, 'ai_reply', 1, [
                'conversation_id' => $conversationId,
            ]);
        } catch (\InvalidArgumentException) {
            // limit exceeded mid-flight — still send the reply we already generated
        }

        return $reply;
    }

    private function defaultReply(): string
    {
        return 'ধন্যবাদ আপনার message-এর জন্য। 😊 আপনার প্রশ্নটি একটু বিস্তারিত লিখবেন কি? Product name, screenshot, বা details পাঠালে আমরা ভালোভাবে সাহায্য করতে পারব।';
    }

    private function markCompleted(string $message): void
    {
        $this->webhookEvent->update(['status' => 'completed', 'processed_at' => now()]);
        WebhookEventLog::create([
            'webhook_event_id' => $this->webhookEvent->id,
            'status' => 'completed',
            'message' => $message,
        ]);
    }

    private function markIgnored(string $message): void
    {
        $this->webhookEvent->update(['status' => 'ignored', 'processed_at' => now()]);
        WebhookEventLog::create([
            'webhook_event_id' => $this->webhookEvent->id,
            'status' => 'ignored',
            'message' => $message,
        ]);
    }

    private function markFailed(string $message): void
    {
        $this->webhookEvent->update(['status' => 'failed', 'error_message' => $message, 'processed_at' => now()]);
        WebhookEventLog::create([
            'webhook_event_id' => $this->webhookEvent->id,
            'status' => 'failed',
            'message' => $message,
        ]);
    }
}
