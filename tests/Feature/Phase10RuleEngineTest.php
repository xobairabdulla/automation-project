<?php

namespace Tests\Feature;

use App\Jobs\ProcessIncomingCommentJob;
use App\Jobs\ProcessIncomingMessageJob;
use App\Jobs\SendFacebookCommentReplyJob;
use App\Jobs\SendFacebookMessageJob;
use App\Models\AutomationRule;
use App\Models\AutomationRuleAction;
use App\Models\AutomationRuleCondition;
use App\Models\FacebookPage;
use App\Models\Plan;
use App\Models\ReplyTemplate;
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
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class Phase10RuleEngineTest extends TestCase
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

    private function createRule(array $conditionKeywords, string $actionType, ?string $replyText = null, string $channel = 'both', int $priority = 10): AutomationRule
    {
        $rule = AutomationRule::create([
            'tenant_id' => $this->user->id,
            'facebook_page_id' => $this->page->id,
            'name' => 'Test Rule',
            'channel' => $channel,
            'priority' => $priority,
            'status' => 'active',
        ]);

        AutomationRuleCondition::create([
            'automation_rule_id' => $rule->id,
            'condition_type' => 'keyword_contains',
            'keywords_json' => $conditionKeywords,
            'case_sensitive' => false,
        ]);

        AutomationRuleAction::create([
            'automation_rule_id' => $rule->id,
            'action_type' => $actionType,
            'reply_text' => $replyText,
        ]);

        return $rule;
    }

    // RuleEngineService unit-style tests

    public function test_rule_matches_keyword_contains(): void
    {
        $this->createRule(['price', 'cost'], 'send_text', 'Our prices start at $10.');

        $service = app(RuleEngineService::class);
        $action = $service->findMatchingAction($this->page, 'message', 'What is the price?');

        $this->assertNotNull($action);
        $this->assertEquals('send_text', $action->action_type);
        $this->assertEquals('Our prices start at $10.', $action->reply_text);
    }

    public function test_rule_matches_exact_match(): void
    {
        $rule = AutomationRule::create([
            'tenant_id' => $this->user->id,
            'facebook_page_id' => $this->page->id,
            'name' => 'Exact',
            'channel' => 'message',
            'priority' => 5,
            'status' => 'active',
        ]);
        AutomationRuleCondition::create([
            'automation_rule_id' => $rule->id,
            'condition_type' => 'exact_match',
            'keywords_json' => ['hello'],
            'case_sensitive' => false,
        ]);
        AutomationRuleAction::create([
            'automation_rule_id' => $rule->id,
            'action_type' => 'send_text',
            'reply_text' => 'Hi there!',
        ]);

        $service = app(RuleEngineService::class);
        $this->assertNotNull($service->findMatchingAction($this->page, 'message', 'Hello'));
        $this->assertNull($service->findMatchingAction($this->page, 'message', 'Hello world'));
    }

    public function test_rule_matches_starts_with(): void
    {
        $rule = AutomationRule::create([
            'tenant_id' => $this->user->id,
            'facebook_page_id' => $this->page->id,
            'name' => 'Starts',
            'channel' => 'message',
            'priority' => 5,
            'status' => 'active',
        ]);
        AutomationRuleCondition::create([
            'automation_rule_id' => $rule->id,
            'condition_type' => 'starts_with',
            'keywords_json' => ['order'],
            'case_sensitive' => false,
        ]);
        AutomationRuleAction::create([
            'automation_rule_id' => $rule->id,
            'action_type' => 'send_text',
            'reply_text' => 'Order info...',
        ]);

        $service = app(RuleEngineService::class);
        $this->assertNotNull($service->findMatchingAction($this->page, 'message', 'Order number 123'));
        $this->assertNull($service->findMatchingAction($this->page, 'message', 'My order'));
    }

    public function test_higher_priority_rule_wins(): void
    {
        $this->createRule(['hello'], 'send_text', 'Low priority reply', 'message', 20);
        $this->createRule(['hello'], 'send_text', 'High priority reply', 'message', 5);

        $service = app(RuleEngineService::class);
        $action = $service->findMatchingAction($this->page, 'message', 'hello');

        $this->assertEquals('High priority reply', $action?->reply_text);
    }

    public function test_inactive_rule_not_matched(): void
    {
        $rule = $this->createRule(['hello'], 'send_text', 'Reply');
        $rule->update(['status' => 'inactive']);

        $service = app(RuleEngineService::class);
        $this->assertNull($service->findMatchingAction($this->page, 'message', 'hello'));
    }

    public function test_rule_channel_message_not_matched_for_comment(): void
    {
        $this->createRule(['hello'], 'send_text', 'Reply', 'message');

        $service = app(RuleEngineService::class);
        $this->assertNull($service->findMatchingAction($this->page, 'comment', 'hello'));
    }

    public function test_rule_channel_both_matches_message_and_comment(): void
    {
        $this->createRule(['hello'], 'send_text', 'Reply', 'both');

        $service = app(RuleEngineService::class);
        $this->assertNotNull($service->findMatchingAction($this->page, 'message', 'hello'));
        $this->assertNotNull($service->findMatchingAction($this->page, 'comment', 'hello'));
    }

    public function test_send_template_action_resolves_template_body(): void
    {
        $template = ReplyTemplate::create([
            'tenant_id' => $this->user->id,
            'facebook_page_id' => $this->page->id,
            'name' => 'FAQ Template',
            'channel' => 'both',
            'language' => 'en',
            'body' => 'Template body text',
            'status' => 'active',
        ]);

        $rule = AutomationRule::create([
            'tenant_id' => $this->user->id,
            'facebook_page_id' => $this->page->id,
            'name' => 'Template Rule',
            'channel' => 'message',
            'priority' => 5,
            'status' => 'active',
        ]);
        AutomationRuleCondition::create([
            'automation_rule_id' => $rule->id,
            'condition_type' => 'keyword_contains',
            'keywords_json' => ['faq'],
            'case_sensitive' => false,
        ]);
        AutomationRuleAction::create([
            'automation_rule_id' => $rule->id,
            'action_type' => 'send_template',
            'reply_template_id' => $template->id,
        ]);

        $service = app(RuleEngineService::class);
        $action = $service->findMatchingAction($this->page, 'message', 'faq');

        $this->assertNotNull($action);
        $this->assertEquals('Template body text', $action->resolveReplyText());
    }

    // Integration tests: jobs use rule engine

    public function test_message_job_uses_rule_reply_text(): void
    {
        Queue::fake();

        $this->createRule(['price'], 'send_text', 'Price is $50.', 'message');

        $event = WebhookEvent::factory()
            ->withMessagePayload('SENDER_1', 'What is the price?', 'mid_1')
            ->create([
                'tenant_id' => $this->user->id,
                'facebook_page_id' => $this->page->id,
            ]);

        $job = new ProcessIncomingMessageJob($event);
        $job->handle(app(ConversationService::class), app(UsageLimitService::class), app(RuleEngineService::class), app(AIReplyService::class), app(HumanHandoverService::class));

        Queue::assertPushed(SendFacebookMessageJob::class, function ($job) {
            return $job->replyText === 'Price is $50.';
        });
    }

    public function test_message_job_uses_default_reply_when_no_rule_matches(): void
    {
        Queue::fake();

        $event = WebhookEvent::factory()
            ->withMessagePayload('SENDER_2', 'Hello there!', 'mid_2')
            ->create([
                'tenant_id' => $this->user->id,
                'facebook_page_id' => $this->page->id,
            ]);

        $job = new ProcessIncomingMessageJob($event);
        $job->handle(app(ConversationService::class), app(UsageLimitService::class), app(RuleEngineService::class), app(AIReplyService::class), app(HumanHandoverService::class));

        Queue::assertPushed(SendFacebookMessageJob::class, function ($job) {
            return str_contains($job->replyText, 'Thank you');
        });
    }

    public function test_message_job_do_nothing_rule_skips_reply(): void
    {
        Queue::fake();

        $this->createRule(['spam'], 'do_nothing', null, 'message');

        $event = WebhookEvent::factory()
            ->withMessagePayload('SENDER_3', 'spam message', 'mid_3')
            ->create([
                'tenant_id' => $this->user->id,
                'facebook_page_id' => $this->page->id,
            ]);

        $job = new ProcessIncomingMessageJob($event);
        $job->handle(app(ConversationService::class), app(UsageLimitService::class), app(RuleEngineService::class), app(AIReplyService::class), app(HumanHandoverService::class));

        Queue::assertNotPushed(SendFacebookMessageJob::class);
    }

    public function test_comment_job_uses_rule_reply_text(): void
    {
        Queue::fake();

        $this->createRule(['delivery'], 'send_text', 'We deliver in 3-5 days.', 'comment');

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
                        'comment_id' => 'C1',
                        'post_id' => 'P1',
                        'message' => 'What about delivery?',
                        'from' => ['id' => 'U1', 'name' => 'User'],
                        'created_time' => now()->timestamp,
                    ],
                ]],
            ],
        ]);

        $job = new ProcessIncomingCommentJob($event);
        $job->handle(app(UsageLimitService::class), app(RuleEngineService::class), app(AIReplyService::class), app(HumanHandoverService::class));

        Queue::assertPushed(SendFacebookCommentReplyJob::class, function ($job) {
            return $job->replyText === 'We deliver in 3-5 days.';
        });
    }
}
