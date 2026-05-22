<?php

namespace Tests\Feature;

use App\Jobs\ProcessIncomingCommentJob;
use App\Jobs\ProcessIncomingMessageJob;
use App\Jobs\SendFacebookCommentReplyJob;
use App\Jobs\SendFacebookMessageJob;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\FacebookPage;
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
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class Phase13HumanHandoverTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private FacebookPage $page;

    private Customer $customer;

    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->page = FacebookPage::factory()->create([
            'user_id' => $this->user->id,
            'message_automation_enabled' => true,
            'comment_automation_enabled' => true,
        ]);
        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->user->id,
            'facebook_page_id' => $this->page->id,
            'external_customer_id' => 'FB_HT_USER',
        ]);
        $this->conversation = Conversation::factory()->create([
            'tenant_id' => $this->user->id,
            'facebook_page_id' => $this->page->id,
            'customer_id' => $this->customer->id,
            'status' => 'open',
            'human_takeover' => false,
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

    // --- HumanHandoverService unit tests ---

    public function test_service_detects_refund_trigger(): void
    {
        $service = app(HumanHandoverService::class);
        $this->assertSame('refund', $service->detectSensitiveTrigger('I want a refund please'));
    }

    public function test_service_detects_human_request_trigger(): void
    {
        $service = app(HumanHandoverService::class);
        $this->assertSame('human_request', $service->detectSensitiveTrigger('Can I speak to a real person?'));
    }

    public function test_service_detects_angry_trigger(): void
    {
        $service = app(HumanHandoverService::class);
        $this->assertSame('angry', $service->detectSensitiveTrigger('I am furious about this order!'));
    }

    public function test_service_returns_null_for_normal_text(): void
    {
        $service = app(HumanHandoverService::class);
        $this->assertNull($service->detectSensitiveTrigger('What are your opening hours?'));
    }

    public function test_trigger_handover_sets_fields_and_notifies(): void
    {
        Notification::fake();

        $service = app(HumanHandoverService::class);
        $service->triggerHandover($this->conversation, 'refund', 'automation');

        $this->conversation->refresh();
        $this->assertTrue($this->conversation->human_takeover);
        $this->assertSame('refund', $this->conversation->human_takeover_reason);
        $this->assertNotNull($this->conversation->human_takeover_at);

        Notification::assertSentTo($this->user, \App\Notifications\HumanHandoverNotification::class);
    }

    public function test_trigger_handover_is_idempotent(): void
    {
        Notification::fake();
        $service = app(HumanHandoverService::class);

        $service->triggerHandover($this->conversation, 'refund');
        $service->triggerHandover($this->conversation, 'complaint');

        // Second call skipped — reason stays as 'refund'
        $this->assertSame('refund', $this->conversation->fresh()->human_takeover_reason);
        Notification::assertSentToTimes($this->user, \App\Notifications\HumanHandoverNotification::class, 1);
    }

    public function test_release_handover_clears_all_fields(): void
    {
        $this->conversation->update([
            'human_takeover' => true,
            'human_takeover_reason' => 'complaint',
            'human_takeover_at' => now(),
        ]);

        $service = app(HumanHandoverService::class);
        $service->releaseHandover($this->conversation);

        $this->conversation->refresh();
        $this->assertFalse($this->conversation->human_takeover);
        $this->assertNull($this->conversation->human_takeover_reason);
        $this->assertNull($this->conversation->human_takeover_at);
    }

    // --- API endpoint tests ---

    public function test_enable_handover_endpoint(): void
    {
        Notification::fake();
        $response = $this->actingAs($this->user)->postJson(
            "/inbox/conversations/{$this->conversation->id}/human-takeover/enable",
            ['reason' => 'Customer requested human']
        );

        $response->assertOk();
        $this->assertTrue($this->conversation->fresh()->human_takeover);
        $this->assertSame('Customer requested human', $this->conversation->fresh()->human_takeover_reason);
    }

    public function test_disable_handover_endpoint(): void
    {
        $this->conversation->update(['human_takeover' => true, 'human_takeover_reason' => 'test']);

        $response = $this->actingAs($this->user)->postJson(
            "/inbox/conversations/{$this->conversation->id}/human-takeover/disable"
        );

        $response->assertOk();
        $this->assertFalse($this->conversation->fresh()->human_takeover);
    }

    public function test_cannot_enable_handover_on_other_tenants_conversation(): void
    {
        $other = User::factory()->create();
        $response = $this->actingAs($other)->postJson(
            "/inbox/conversations/{$this->conversation->id}/human-takeover/enable"
        );
        $response->assertForbidden();
    }

    // --- Job integration tests ---

    public function test_message_job_triggers_handover_on_refund_keyword(): void
    {
        Queue::fake();
        Notification::fake();

        $event = WebhookEvent::factory()
            ->withMessagePayload('FB_HT_USER', 'I want a refund for my order', 'mid_refund')
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

        Queue::assertNotPushed(SendFacebookMessageJob::class);

        $conv = Conversation::where('facebook_page_id', $this->page->id)
            ->where('customer_id', $this->customer->id)
            ->first();
        $this->assertTrue($conv->human_takeover);
        $this->assertSame('refund', $conv->human_takeover_reason);
    }

    public function test_message_job_triggers_handover_on_human_request(): void
    {
        Queue::fake();
        Notification::fake();

        $event = WebhookEvent::factory()
            ->withMessagePayload('FB_HT_USER', 'I want to speak to a real person', 'mid_human')
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

        Queue::assertNotPushed(SendFacebookMessageJob::class);

        $conv = Conversation::where('facebook_page_id', $this->page->id)
            ->where('customer_id', $this->customer->id)
            ->first();
        $this->assertTrue($conv->human_takeover);
    }

    public function test_comment_job_flags_human_review_on_sensitive_keyword(): void
    {
        Queue::fake();
        Notification::fake();

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
                        'comment_id' => 'C_COMPLAINT',
                        'post_id' => 'P1',
                        'message' => 'This is unacceptable, I will complain!',
                        'from' => ['id' => 'U1', 'name' => 'User'],
                        'created_time' => now()->timestamp,
                    ],
                ]],
            ],
        ]);

        $job = new ProcessIncomingCommentJob($event);
        $job->handle(
            app(UsageLimitService::class),
            app(RuleEngineService::class),
            app(AIReplyService::class),
            app(HumanHandoverService::class),
        );

        Queue::assertNotPushed(SendFacebookCommentReplyJob::class);
        $this->assertDatabaseHas('facebook_comments', [
            'external_comment_id' => 'C_COMPLAINT',
            'status' => 'human_review',
        ]);
    }

    public function test_no_auto_reply_when_human_takeover_already_active(): void
    {
        Queue::fake();

        $this->conversation->update(['human_takeover' => true]);

        $event = WebhookEvent::factory()
            ->withMessagePayload('FB_HT_USER', 'Hello again', 'mid_ht2')
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

        Queue::assertNotPushed(SendFacebookMessageJob::class);
    }

    public function test_notifications_endpoint_returns_unread(): void
    {
        Notification::fake();
        $service = app(HumanHandoverService::class);
        $service->triggerHandover($this->conversation, 'refund');

        $response = $this->actingAs($this->user)->getJson('/inbox/notifications');
        $response->assertOk();
    }
}
