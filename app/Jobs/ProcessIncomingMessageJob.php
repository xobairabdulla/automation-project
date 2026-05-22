<?php

namespace App\Jobs;

use App\Models\FacebookPage;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Models\WebhookEventLog;
use App\Models\AiPrompt;
use App\Services\AIReplyService;
use App\Services\ConversationService;
use App\Services\HumanHandoverService;
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

    public function handle(ConversationService $conversationService, UsageLimitService $usageLimitService, RuleEngineService $ruleEngineService, AIReplyService $aiReplyService, HumanHandoverService $handoverService): void
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
        $conversation = $conversationService->findOrCreateConversation($page, $customer);
        $conversationService->saveIncomingMessage($conversation, $messageText, $messageId ?? '');

        if (! $page->message_automation_enabled) {
            $this->markCompleted('Message automation disabled for page.');

            return;
        }

        if ($conversation->human_takeover) {
            $this->markCompleted('Human takeover active — no auto reply.');

            return;
        }

        $user = User::find($page->user_id);

        if (! $user) {
            $this->markFailed('User not found for page.');

            return;
        }

        if (! $usageLimitService->canSendMessageReply($user)) {
            $this->markCompleted('Message reply usage limit reached.');

            return;
        }

        try {
            $usageLimitService->incrementUsage($user, 'message_reply', 1, [
                'conversation_id' => $conversation->id,
                'customer_id' => $customer->id,
            ]);
        } catch (\InvalidArgumentException $e) {
            $this->markCompleted('Usage limit check failed: '.$e->getMessage());

            return;
        }

        $sensitiveTrigger = $handoverService->detectSensitiveTrigger($messageText);
        if ($sensitiveTrigger) {
            $handoverService->triggerHandover($conversation, $sensitiveTrigger);
            $this->markCompleted("Sensitive trigger detected: {$sensitiveTrigger} — human handover triggered.");

            return;
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

        if ($replyText === null) {
            $replyText = $this->defaultReply();
        }

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
        \App\Models\FacebookPage $page,
        \App\Models\User $user,
        int $conversationId,
        string $messageText,
        UsageLimitService $usageLimitService,
        AIReplyService $aiReplyService,
        \App\Models\Conversation $conversation,
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
        return 'Thank you for your message! We will get back to you shortly.';
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
