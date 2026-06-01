<?php

namespace App\Jobs;

use App\Models\AiPrompt;
use App\Models\FacebookComment;
use App\Models\FacebookPage;
use App\Models\FacebookPost;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Models\WebhookEventLog;
use App\Services\AIReplyService;
use App\Services\FacebookProductReplyService;
use App\Services\HumanHandoverService;
use App\Services\ProductMatcherService;
use App\Services\RuleEngineService;
use App\Services\UsageLimitService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessIncomingCommentJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public readonly WebhookEvent $webhookEvent) {}

    public function handle(UsageLimitService $usageLimitService, RuleEngineService $ruleEngineService, AIReplyService $aiReplyService, HumanHandoverService $handoverService, ProductMatcherService $productMatcher, FacebookProductReplyService $productReplyService): void
    {
        $payload = $this->webhookEvent->payload_json;
        $change = $payload['changes'][0] ?? null;

        if (! $change || ($change['field'] ?? '') !== 'feed') {
            $this->markIgnored('Not a feed change event.');

            return;
        }

        $value = $change['value'] ?? [];

        if (($value['item'] ?? '') !== 'comment' || ($value['verb'] ?? '') !== 'add') {
            $this->markIgnored('Not a new comment event.');

            return;
        }

        $commentId = $value['comment_id'] ?? null;
        $commentText = $value['message'] ?? null;
        $commenterId = $value['from']['id'] ?? null;
        $commenterName = $value['from']['name'] ?? null;
        $postId = $value['post_id'] ?? null;
        $createdTime = isset($value['created_time']) ? Carbon::createFromTimestamp($value['created_time']) : null;

        if (! $commentId || ! $postId) {
            $this->markIgnored('Missing comment_id or post_id.');

            return;
        }

        $page = FacebookPage::find($this->webhookEvent->facebook_page_id);

        if (! $page) {
            $this->markFailed('Facebook page not found.');

            return;
        }

        $post = FacebookPost::firstOrCreate(
            ['facebook_page_id' => $page->id, 'external_post_id' => $postId],
            [
                'tenant_id' => $page->user_id,
                'permalink_url' => $value['post']['permalink_url'] ?? null,
                'created_time' => $createdTime,
            ]
        );

        $comment = FacebookComment::firstOrCreate(
            ['facebook_page_id' => $page->id, 'external_comment_id' => $commentId],
            [
                'tenant_id' => $page->user_id,
                'facebook_post_id' => $post->id,
                'commenter_id' => $commenterId,
                'commenter_name' => $commenterName,
                'comment_text' => $commentText,
                'status' => 'new',
                'created_time' => $createdTime,
            ]
        );

        if (! $page->comment_automation_enabled) {
            $this->markCompleted('Comment automation disabled for page.');

            return;
        }

        $user = User::find($page->user_id);

        if (! $user) {
            $this->markFailed('User not found for page.');

            return;
        }

        if (! $usageLimitService->canSendCommentReply($user)) {
            $comment->update(['status' => 'ignored']);
            $this->markCompleted('Comment reply usage limit reached.');

            return;
        }

        try {
            $usageLimitService->incrementUsage($user, 'comment_reply', 1, [
                'facebook_comment_id' => $comment->id,
                'facebook_post_id' => $post->id,
            ]);
        } catch (\InvalidArgumentException $e) {
            $comment->update(['status' => 'ignored']);
            $this->markCompleted('Usage limit check failed: '.$e->getMessage());

            return;
        }

        $sensitiveTrigger = $handoverService->detectSensitiveTrigger($commentText ?? '');
        if ($sensitiveTrigger) {
            $comment->update(['status' => 'human_review']);
            $this->markCompleted("Sensitive trigger detected: {$sensitiveTrigger} — comment flagged for human review.");

            return;
        }

        // Product image inquiry — handle before rule engine
        if ($commentText && $productMatcher->hasProductImageIntent($commentText)) {
            $handled = $productReplyService->handleCommentInquiry($page, $comment, $commenterId ?? '');
            if ($handled) {
                $comment->update(['status' => 'replied']);
                $this->markCompleted('Product image comment inquiry handled.');

                return;
            }
        }

        $action = $ruleEngineService->findMatchingAction($page, 'comment', $commentText ?? '');

        if ($action && $action->action_type === 'do_nothing') {
            $comment->update(['status' => 'ignored']);
            $this->markCompleted('Rule matched: do_nothing action.');

            return;
        }

        if ($action && $action->action_type === 'human_handover') {
            $comment->update(['status' => 'human_review']);
            $this->markCompleted('Rule matched: human_handover triggered.');

            return;
        }

        $replyText = $action?->resolveReplyText();

        if ($replyText === null) {
            $user = User::find($page->user_id);
            if ($user && $usageLimitService->canGenerateAIReply($user)) {
                $aiGenerated = $aiReplyService->generateReply($page, $commentText ?? '', null, $comment->id);
                if ($aiGenerated === null) {
                    $aiPrompt = AiPrompt::where('facebook_page_id', $page->id)->first();
                    if (($aiPrompt?->unknown_answer_action ?? 'send_default') === 'human_handover') {
                        $comment->update(['status' => 'human_review']);
                        $this->markCompleted('AI unknown answer — comment flagged for human review.');

                        return;
                    }
                } else {
                    try {
                        $usageLimitService->incrementUsage($user, 'ai_reply', 1, ['facebook_comment_id' => $comment->id]);
                    } catch (\InvalidArgumentException) {
                    }
                    $replyText = $aiGenerated;
                }
            }
        }

        $replyText ??= $this->defaultReply();

        SendFacebookCommentReplyJob::dispatch($page, $comment, $replyText);

        $this->markCompleted('Incoming comment processed, reply dispatched.');
    }

    public function failed(\Throwable $e): void
    {
        Log::error('ProcessIncomingCommentJob failed', [
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

    private function defaultReply(): string
    {
        return 'ধন্যবাদ আপনার comment-এর জন্য। 😊 আপনার প্রশ্নটি একটু বিস্তারিত লিখবেন কি? Product name, screenshot, বা details পাঠালে আমরা ভালোভাবে সাহায্য করতে পারব।';
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
