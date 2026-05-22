<?php

namespace Tests\Feature;

use App\Jobs\ProcessIncomingCommentJob;
use App\Jobs\ProcessIncomingMessageJob;
use App\Jobs\SendFacebookCommentReplyJob;
use App\Jobs\SendFacebookMessageJob;
use App\Models\AiPrompt;
use App\Models\FacebookPage;
use App\Models\KnowledgeBase;
use App\Models\KnowledgeBaseItem;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\UsageLimit;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\AIReplyService;
use App\Services\ConversationService;
use App\Services\HumanHandoverService;
use App\Services\RuleEngineService;
use App\Services\UsageLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class Phase11AIReplyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private FacebookPage $page;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->page = FacebookPage::factory()->create([
            'user_id' => $this->user->id,
            'message_automation_enabled' => true,
            'comment_automation_enabled' => true,
        ]);

        $plan = Plan::factory()->create([
            'message_reply_limit' => 100,
            'comment_reply_limit' => 100,
            'ai_reply_limit' => 50,
        ]);

        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);

        UsageLimit::factory()->create([
            'user_id' => $this->user->id,
            'subscription_id' => $subscription->id,
            'message_reply_limit' => 100,
            'message_reply_used' => 0,
            'comment_reply_limit' => 100,
            'comment_reply_used' => 0,
            'ai_reply_limit' => 50,
            'ai_reply_used' => 0,
            'reset_at' => now()->addMonth(),
        ]);
    }

    private function seedKnowledgeBase(string $answer = 'We deliver in 3-5 business days.'): void
    {
        $kb = KnowledgeBase::create([
            'tenant_id' => $this->user->id,
            'facebook_page_id' => $this->page->id,
            'name' => 'Main KB',
            'status' => 'active',
        ]);

        KnowledgeBaseItem::create([
            'tenant_id' => $this->user->id,
            'knowledge_base_id' => $kb->id,
            'title' => 'Delivery time',
            'category' => 'delivery',
            'content' => $answer,
            'status' => 'active',
        ]);
    }

    private function fakeOpenAiSuccess(string $replyText): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => $replyText]]],
                'usage' => ['total_tokens' => 42],
            ], 200),
        ]);
    }

    private function fakeOpenAiUnknown(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => '__UNKNOWN__']]],
                'usage' => ['total_tokens' => 10],
            ], 200),
        ]);
    }

    public function test_ai_reply_service_returns_reply_from_knowledge_base(): void
    {
        $this->seedKnowledgeBase('We deliver in 3-5 business days.');
        $this->fakeOpenAiSuccess('We deliver in 3-5 business days.');

        config(['services.ai.openai_api_key' => 'fake-key']);

        $service = app(AIReplyService::class);
        $reply = $service->generateReply($this->page, 'How long is delivery?');

        $this->assertSame('We deliver in 3-5 business days.', $reply);
    }

    public function test_ai_reply_service_returns_null_for_unknown_answer(): void
    {
        $this->seedKnowledgeBase();
        $this->fakeOpenAiUnknown();

        config(['services.ai.openai_api_key' => 'fake-key']);

        $service = app(AIReplyService::class);
        $reply = $service->generateReply($this->page, 'What is the meaning of life?');

        $this->assertNull($reply);
        $this->assertDatabaseHas('ai_reply_logs', [
            'facebook_page_id' => $this->page->id,
            'status' => 'unknown_answer',
        ]);
    }

    public function test_ai_reply_service_returns_null_when_no_api_key(): void
    {
        config(['services.ai.openai_api_key' => null]);

        $service = app(AIReplyService::class);
        $reply = $service->generateReply($this->page, 'Hello?');

        $this->assertNull($reply);
        $this->assertDatabaseHas('ai_reply_logs', [
            'facebook_page_id' => $this->page->id,
            'status' => 'failed',
        ]);
    }

    public function test_ai_reply_logged_on_success(): void
    {
        $this->seedKnowledgeBase();
        $this->fakeOpenAiSuccess('Here is your answer.');
        config(['services.ai.openai_api_key' => 'fake-key']);

        $service = app(AIReplyService::class);
        $service->generateReply($this->page, 'Question?');

        $this->assertDatabaseHas('ai_reply_logs', [
            'tenant_id' => $this->user->id,
            'facebook_page_id' => $this->page->id,
            'status' => 'success',
            'tokens_used' => 42,
        ]);
    }

    public function test_message_job_uses_ai_reply_when_no_rule_matches(): void
    {
        Queue::fake();
        $this->seedKnowledgeBase();
        $this->fakeOpenAiSuccess('AI generated reply.');
        config(['services.ai.openai_api_key' => 'fake-key']);

        $event = WebhookEvent::factory()
            ->withMessagePayload('SENDER_AI', 'What are your hours?', 'mid_ai')
            ->create([
                'tenant_id' => $this->user->id,
                'facebook_page_id' => $this->page->id,
            ]);

        $job = new ProcessIncomingMessageJob($event);
        $job->handle(
            app(ConversationService::class),
            app(UsageLimitService::class),
            app(RuleEngineService::class),
            app(AIReplyService::class),
            app(HumanHandoverService::class),
        );

        Queue::assertPushed(SendFacebookMessageJob::class, function ($job) {
            return $job->replyText === 'AI generated reply.';
        });
    }

    public function test_message_job_increments_ai_usage_on_ai_reply(): void
    {
        Queue::fake();
        $this->seedKnowledgeBase();
        $this->fakeOpenAiSuccess('AI reply here.');
        config(['services.ai.openai_api_key' => 'fake-key']);

        $event = WebhookEvent::factory()
            ->withMessagePayload('SENDER_AIUSE', 'Question', 'mid_aiuse')
            ->create([
                'tenant_id' => $this->user->id,
                'facebook_page_id' => $this->page->id,
            ]);

        $job = new ProcessIncomingMessageJob($event);
        $job->handle(
            app(ConversationService::class),
            app(UsageLimitService::class),
            app(RuleEngineService::class),
            app(AIReplyService::class),
            app(HumanHandoverService::class),
        );

        $this->assertDatabaseHas('usage_logs', [
            'user_id' => $this->user->id,
            'type' => 'ai_reply',
        ]);
    }

    public function test_message_job_skips_ai_when_ai_limit_exceeded(): void
    {
        Queue::fake();
        $this->seedKnowledgeBase();
        config(['services.ai.openai_api_key' => 'fake-key']);

        UsageLimit::where('user_id', $this->user->id)->update([
            'ai_reply_used' => 50,
            'ai_reply_limit' => 50,
        ]);

        $event = WebhookEvent::factory()
            ->withMessagePayload('SENDER_AILIMIT', 'Question', 'mid_ailimit')
            ->create([
                'tenant_id' => $this->user->id,
                'facebook_page_id' => $this->page->id,
            ]);

        $job = new ProcessIncomingMessageJob($event);
        $job->handle(
            app(ConversationService::class),
            app(UsageLimitService::class),
            app(RuleEngineService::class),
            app(AIReplyService::class),
            app(HumanHandoverService::class),
        );

        Queue::assertPushed(SendFacebookMessageJob::class, function ($job) {
            return str_contains($job->replyText, 'Thank you');
        });

        $this->assertDatabaseMissing('usage_logs', [
            'user_id' => $this->user->id,
            'type' => 'ai_reply',
        ]);
    }

    public function test_message_job_triggers_human_takeover_on_unknown_answer(): void
    {
        Queue::fake();
        $this->seedKnowledgeBase();
        $this->fakeOpenAiUnknown();
        config(['services.ai.openai_api_key' => 'fake-key']);

        AiPrompt::create([
            'tenant_id' => $this->user->id,
            'facebook_page_id' => $this->page->id,
            'preferred_language' => 'en',
            'tone' => 'friendly',
            'reply_length' => 'short',
            'use_emoji' => false,
            'unknown_answer_action' => 'human_handover',
        ]);

        $event = WebhookEvent::factory()
            ->withMessagePayload('SENDER_UNKNOWN', 'Some unknown question', 'mid_unk')
            ->create([
                'tenant_id' => $this->user->id,
                'facebook_page_id' => $this->page->id,
            ]);

        $job = new ProcessIncomingMessageJob($event);
        $job->handle(
            app(ConversationService::class),
            app(UsageLimitService::class),
            app(RuleEngineService::class),
            app(AIReplyService::class),
            app(HumanHandoverService::class),
        );

        $this->assertDatabaseHas('conversations', [
            'facebook_page_id' => $this->page->id,
            'human_takeover' => true,
        ]);

        Queue::assertNotPushed(SendFacebookMessageJob::class);
    }

    public function test_comment_job_uses_ai_reply_when_no_rule_matches(): void
    {
        Queue::fake();
        $this->seedKnowledgeBase();
        $this->fakeOpenAiSuccess('AI comment reply.');
        config(['services.ai.openai_api_key' => 'fake-key']);

        $event = WebhookEvent::factory()->create([
            'tenant_id' => $this->user->id,
            'facebook_page_id' => $this->page->id,
            'event_type' => 'comment_feed',
            'payload_json' => [
                'changes' => [[
                    'field' => 'feed',
                    'value' => [
                        'item' => 'comment',
                        'verb' => 'add',
                        'comment_id' => 'C_AI_1',
                        'post_id' => 'P_AI_1',
                        'message' => 'What are your hours?',
                        'from' => ['id' => 'U_AI', 'name' => 'User'],
                        'created_time' => now()->timestamp,
                    ],
                ]],
            ],
        ]);

        $job = new ProcessIncomingCommentJob($event);
        $job->handle(app(UsageLimitService::class), app(RuleEngineService::class), app(AIReplyService::class), app(HumanHandoverService::class));

        Queue::assertPushed(SendFacebookCommentReplyJob::class, function ($job) {
            return $job->replyText === 'AI comment reply.';
        });
    }

    public function test_comment_job_flags_human_review_on_unknown_ai_answer(): void
    {
        Queue::fake();
        $this->seedKnowledgeBase();
        $this->fakeOpenAiUnknown();
        config(['services.ai.openai_api_key' => 'fake-key']);

        AiPrompt::create([
            'tenant_id' => $this->user->id,
            'facebook_page_id' => $this->page->id,
            'preferred_language' => 'en',
            'tone' => 'friendly',
            'reply_length' => 'short',
            'use_emoji' => false,
            'unknown_answer_action' => 'human_handover',
        ]);

        $event = WebhookEvent::factory()->create([
            'tenant_id' => $this->user->id,
            'facebook_page_id' => $this->page->id,
            'event_type' => 'comment_feed',
            'payload_json' => [
                'changes' => [[
                    'field' => 'feed',
                    'value' => [
                        'item' => 'comment',
                        'verb' => 'add',
                        'comment_id' => 'C_UNKNOWN_1',
                        'post_id' => 'P_UNKNOWN_1',
                        'message' => 'Unknown question here',
                        'from' => ['id' => 'U_UNK', 'name' => 'User'],
                        'created_time' => now()->timestamp,
                    ],
                ]],
            ],
        ]);

        $job = new ProcessIncomingCommentJob($event);
        $job->handle(app(UsageLimitService::class), app(RuleEngineService::class), app(AIReplyService::class), app(HumanHandoverService::class));

        $this->assertDatabaseHas('facebook_comments', [
            'external_comment_id' => 'C_UNKNOWN_1',
            'status' => 'human_review',
        ]);

        Queue::assertNotPushed(SendFacebookCommentReplyJob::class);
    }

    public function test_ai_settings_test_reply_endpoint(): void
    {
        $this->seedKnowledgeBase();
        $this->fakeOpenAiSuccess('Here is your answer.');
        config(['services.ai.openai_api_key' => 'fake-key']);

        $this->actingAs($this->user);

        $response = $this->postJson('/ai-settings/test-reply', [
            'facebook_page_id' => $this->page->id,
            'question' => 'What are your hours?',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['reply', 'unknown']);
        $response->assertJson(['unknown' => false]);
    }

    public function test_ai_settings_test_reply_returns_unknown_flag(): void
    {
        $this->seedKnowledgeBase();
        $this->fakeOpenAiUnknown();
        config(['services.ai.openai_api_key' => 'fake-key']);

        $this->actingAs($this->user);

        $response = $this->postJson('/ai-settings/test-reply', [
            'facebook_page_id' => $this->page->id,
            'question' => 'Unknown question',
        ]);

        $response->assertOk();
        $response->assertJson(['unknown' => true]);
    }
}
