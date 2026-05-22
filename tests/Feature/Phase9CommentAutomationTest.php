<?php

namespace Tests\Feature;

use App\Jobs\ProcessIncomingCommentJob;
use App\Jobs\SendFacebookCommentReplyJob;
use App\Models\FacebookPage;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\UsageLimit;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\AIReplyService;
use App\Services\HumanHandoverService;
use App\Services\RuleEngineService;
use App\Services\UsageLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class Phase9CommentAutomationTest extends TestCase
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

    private function makeCommentEvent(array $overrides = []): WebhookEvent
    {
        $postId = $overrides['post_id'] ?? '123456789_987654321';
        $commentId = $overrides['comment_id'] ?? fake()->numerify('##########');
        $commentText = $overrides['comment_text'] ?? 'Great post!';
        $commenterId = $overrides['commenter_id'] ?? fake()->numerify('##########');
        $commenterName = $overrides['commenter_name'] ?? 'Test User';

        return WebhookEvent::factory()->create([
            'tenant_id' => $this->user->id,
            'facebook_page_id' => $this->page->id,
            'event_type' => 'comment_feed',
            'payload_json' => [
                'id' => $this->page->page_id,
                'changes' => [
                    [
                        'field' => 'feed',
                        'value' => [
                            'item' => 'comment',
                            'verb' => 'add',
                            'comment_id' => $commentId,
                            'post_id' => $postId,
                            'message' => $commentText,
                            'from' => [
                                'id' => $commenterId,
                                'name' => $commenterName,
                            ],
                            'created_time' => now()->timestamp,
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function test_incoming_comment_creates_post_and_comment_records(): void
    {
        Queue::fake();

        $event = $this->makeCommentEvent([
            'post_id' => '111_222',
            'comment_id' => 'COMMENT_ABC',
            'comment_text' => 'Nice!',
            'commenter_id' => 'USER_1',
            'commenter_name' => 'Alice',
        ]);

        $job = new ProcessIncomingCommentJob($event);
        $job->handle(app(UsageLimitService::class), app(RuleEngineService::class), app(AIReplyService::class), app(HumanHandoverService::class));

        $this->assertDatabaseHas('facebook_posts', [
            'facebook_page_id' => $this->page->id,
            'external_post_id' => '111_222',
        ]);

        $this->assertDatabaseHas('facebook_comments', [
            'facebook_page_id' => $this->page->id,
            'external_comment_id' => 'COMMENT_ABC',
            'commenter_id' => 'USER_1',
            'commenter_name' => 'Alice',
            'comment_text' => 'Nice!',
        ]);
    }

    public function test_reply_dispatched_when_comment_automation_enabled(): void
    {
        Queue::fake();

        $event = $this->makeCommentEvent();
        $job = new ProcessIncomingCommentJob($event);
        $job->handle(app(UsageLimitService::class), app(RuleEngineService::class), app(AIReplyService::class), app(HumanHandoverService::class));

        Queue::assertPushed(SendFacebookCommentReplyJob::class);
    }

    public function test_no_reply_when_comment_automation_disabled(): void
    {
        Queue::fake();

        $this->page->update(['comment_automation_enabled' => false]);

        $event = $this->makeCommentEvent();
        $job = new ProcessIncomingCommentJob($event);
        $job->handle(app(UsageLimitService::class), app(RuleEngineService::class), app(AIReplyService::class), app(HumanHandoverService::class));

        Queue::assertNotPushed(SendFacebookCommentReplyJob::class);
        $this->assertDatabaseHas('webhook_events', ['id' => $event->id, 'status' => 'completed']);
    }

    public function test_no_reply_when_usage_limit_exceeded(): void
    {
        Queue::fake();

        UsageLimit::where('user_id', $this->user->id)->update([
            'comment_reply_used' => 100,
            'comment_reply_limit' => 100,
        ]);

        $event = $this->makeCommentEvent();
        $job = new ProcessIncomingCommentJob($event);
        $job->handle(app(UsageLimitService::class), app(RuleEngineService::class), app(AIReplyService::class), app(HumanHandoverService::class));

        Queue::assertNotPushed(SendFacebookCommentReplyJob::class);
        $this->assertDatabaseHas('webhook_events', ['id' => $event->id, 'status' => 'completed']);
    }

    public function test_usage_incremented_after_reply_dispatched(): void
    {
        Queue::fake();

        $event = $this->makeCommentEvent();
        $job = new ProcessIncomingCommentJob($event);
        $job->handle(app(UsageLimitService::class), app(RuleEngineService::class), app(AIReplyService::class), app(HumanHandoverService::class));

        $this->assertDatabaseHas('usage_logs', [
            'user_id' => $this->user->id,
            'type' => 'comment_reply',
        ]);
    }

    public function test_event_ignored_when_not_feed_change(): void
    {
        Queue::fake();

        $event = WebhookEvent::factory()->create([
            'tenant_id' => $this->user->id,
            'facebook_page_id' => $this->page->id,
            'event_type' => 'comment_feed',
            'payload_json' => [
                'changes' => [
                    ['field' => 'likes', 'value' => []],
                ],
            ],
        ]);

        $job = new ProcessIncomingCommentJob($event);
        $job->handle(app(UsageLimitService::class), app(RuleEngineService::class), app(AIReplyService::class), app(HumanHandoverService::class));

        Queue::assertNotPushed(SendFacebookCommentReplyJob::class);
        $this->assertDatabaseHas('webhook_events', ['id' => $event->id, 'status' => 'ignored']);
    }

    public function test_event_ignored_when_not_new_comment(): void
    {
        Queue::fake();

        $event = WebhookEvent::factory()->create([
            'tenant_id' => $this->user->id,
            'facebook_page_id' => $this->page->id,
            'event_type' => 'comment_feed',
            'payload_json' => [
                'changes' => [
                    [
                        'field' => 'feed',
                        'value' => [
                            'item' => 'comment',
                            'verb' => 'edited',
                            'comment_id' => 'C1',
                            'post_id' => 'P1',
                            'message' => 'edited text',
                            'from' => ['id' => 'U1', 'name' => 'User'],
                        ],
                    ],
                ],
            ],
        ]);

        $job = new ProcessIncomingCommentJob($event);
        $job->handle(app(UsageLimitService::class), app(RuleEngineService::class), app(AIReplyService::class), app(HumanHandoverService::class));

        Queue::assertNotPushed(SendFacebookCommentReplyJob::class);
        $this->assertDatabaseHas('webhook_events', ['id' => $event->id, 'status' => 'ignored']);
    }

    public function test_duplicate_comment_not_reprocessed(): void
    {
        Queue::fake();

        $event1 = $this->makeCommentEvent(['comment_id' => 'DUP_COMMENT', 'post_id' => '111_222']);
        $job1 = new ProcessIncomingCommentJob($event1);
        $job1->handle(app(UsageLimitService::class), app(RuleEngineService::class), app(AIReplyService::class), app(HumanHandoverService::class));

        Queue::assertPushed(SendFacebookCommentReplyJob::class, 1);

        $event2 = $this->makeCommentEvent(['comment_id' => 'DUP_COMMENT', 'post_id' => '111_222']);
        $job2 = new ProcessIncomingCommentJob($event2);
        $job2->handle(app(UsageLimitService::class), app(RuleEngineService::class), app(AIReplyService::class), app(HumanHandoverService::class));

        $this->assertDatabaseCount('facebook_comments', 1);
    }
}
