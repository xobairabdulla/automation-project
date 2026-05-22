<?php

namespace Tests\Feature;

use App\Jobs\ProcessIncomingMessageJob;
use App\Jobs\SendFacebookMessageJob;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\FacebookPage;
use App\Models\Message;
use App\Services\AIReplyService;
use App\Services\HumanHandoverService;
use App\Services\RuleEngineService;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\UsageLimit;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\ConversationService;
use App\Services\UsageLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class Phase8MessageAutomationTest extends TestCase
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

    public function test_incoming_message_creates_customer_and_conversation(): void
    {
        Queue::fake();

        $event = WebhookEvent::factory()
            ->withMessagePayload('SENDER_123', 'Hello!', 'mid_abc')
            ->create([
                'tenant_id' => $this->user->id,
                'facebook_page_id' => $this->page->id,
            ]);

        $job = new ProcessIncomingMessageJob($event);
        $job->handle(app(ConversationService::class), app(UsageLimitService::class), app(RuleEngineService::class), app(AIReplyService::class), app(HumanHandoverService::class));

        $this->assertDatabaseHas('customers', [
            'facebook_page_id' => $this->page->id,
            'external_customer_id' => 'SENDER_123',
        ]);

        $this->assertDatabaseHas('conversations', [
            'facebook_page_id' => $this->page->id,
            'channel' => 'facebook_messenger',
        ]);

        $this->assertDatabaseHas('messages', [
            'direction' => 'incoming',
            'message_text' => 'Hello!',
            'external_message_id' => 'mid_abc',
        ]);
    }

    public function test_reply_is_dispatched_when_automation_enabled(): void
    {
        Queue::fake();

        $event = WebhookEvent::factory()
            ->withMessagePayload('SENDER_456', 'Hi there', 'mid_def')
            ->create([
                'tenant_id' => $this->user->id,
                'facebook_page_id' => $this->page->id,
            ]);

        $job = new ProcessIncomingMessageJob($event);
        $job->handle(app(ConversationService::class), app(UsageLimitService::class), app(RuleEngineService::class), app(AIReplyService::class), app(HumanHandoverService::class));

        Queue::assertPushed(SendFacebookMessageJob::class);
    }

    public function test_no_reply_when_message_automation_disabled(): void
    {
        Queue::fake();

        $this->page->update(['message_automation_enabled' => false]);

        $event = WebhookEvent::factory()
            ->withMessagePayload('SENDER_789', 'Hello', 'mid_ghi')
            ->create([
                'tenant_id' => $this->user->id,
                'facebook_page_id' => $this->page->id,
            ]);

        $job = new ProcessIncomingMessageJob($event);
        $job->handle(app(ConversationService::class), app(UsageLimitService::class), app(RuleEngineService::class), app(AIReplyService::class), app(HumanHandoverService::class));

        Queue::assertNotPushed(SendFacebookMessageJob::class);
    }

    public function test_no_reply_when_human_takeover_active(): void
    {
        Queue::fake();

        $customer = Customer::factory()->create([
            'tenant_id' => $this->user->id,
            'facebook_page_id' => $this->page->id,
            'external_customer_id' => 'SENDER_HT',
        ]);

        Conversation::factory()->humanTakeover()->create([
            'tenant_id' => $this->user->id,
            'facebook_page_id' => $this->page->id,
            'customer_id' => $customer->id,
        ]);

        $event = WebhookEvent::factory()
            ->withMessagePayload('SENDER_HT', 'Help me', 'mid_ht')
            ->create([
                'tenant_id' => $this->user->id,
                'facebook_page_id' => $this->page->id,
            ]);

        $job = new ProcessIncomingMessageJob($event);
        $job->handle(app(ConversationService::class), app(UsageLimitService::class), app(RuleEngineService::class), app(AIReplyService::class), app(HumanHandoverService::class));

        Queue::assertNotPushed(SendFacebookMessageJob::class);
        $this->assertDatabaseHas('webhook_events', ['id' => $event->id, 'status' => 'completed']);
    }

    public function test_no_reply_when_usage_limit_exceeded(): void
    {
        Queue::fake();

        UsageLimit::where('user_id', $this->user->id)->update([
            'message_reply_used' => 100,
            'message_reply_limit' => 100,
        ]);

        $event = WebhookEvent::factory()
            ->withMessagePayload('SENDER_LIMIT', 'Hello', 'mid_limit')
            ->create([
                'tenant_id' => $this->user->id,
                'facebook_page_id' => $this->page->id,
            ]);

        $job = new ProcessIncomingMessageJob($event);
        $job->handle(app(ConversationService::class), app(UsageLimitService::class), app(RuleEngineService::class), app(AIReplyService::class), app(HumanHandoverService::class));

        Queue::assertNotPushed(SendFacebookMessageJob::class);
        $this->assertDatabaseHas('webhook_events', ['id' => $event->id, 'status' => 'completed']);
    }

    public function test_usage_is_incremented_after_reply_dispatched(): void
    {
        Queue::fake();

        $event = WebhookEvent::factory()
            ->withMessagePayload('SENDER_USE', 'Hey', 'mid_use')
            ->create([
                'tenant_id' => $this->user->id,
                'facebook_page_id' => $this->page->id,
            ]);

        $job = new ProcessIncomingMessageJob($event);
        $job->handle(app(ConversationService::class), app(UsageLimitService::class), app(RuleEngineService::class), app(AIReplyService::class), app(HumanHandoverService::class));

        $this->assertDatabaseHas('usage_logs', [
            'user_id' => $this->user->id,
            'type' => 'message_reply',
        ]);
    }

    public function test_event_marked_ignored_when_no_text_in_payload(): void
    {
        Queue::fake();

        $event = WebhookEvent::factory()->create([
            'tenant_id' => $this->user->id,
            'facebook_page_id' => $this->page->id,
            'event_type' => 'message',
            'payload_json' => [
                'messaging' => [
                    ['sender' => ['id' => 'S1'], 'message' => ['mid' => 'm1']],
                ],
            ],
        ]);

        $job = new ProcessIncomingMessageJob($event);
        $job->handle(app(ConversationService::class), app(UsageLimitService::class), app(RuleEngineService::class), app(AIReplyService::class), app(HumanHandoverService::class));

        $this->assertDatabaseHas('webhook_events', ['id' => $event->id, 'status' => 'ignored']);
        Queue::assertNotPushed(SendFacebookMessageJob::class);
    }
}
