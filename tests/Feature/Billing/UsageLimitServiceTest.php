<?php

namespace Tests\Feature\Billing;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\UsageLimit;
use App\Models\User;
use App\Services\UsageLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class UsageLimitServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_checks_and_increments_message_usage(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create([
            'message_reply_limit' => 2,
            'comment_reply_limit' => 2,
            'ai_reply_limit' => 2,
        ]);
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);
        UsageLimit::factory()->create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'message_reply_limit' => 2,
            'message_reply_used' => 1,
        ]);

        $service = app(UsageLimitService::class);

        $this->assertTrue($service->canSendMessageReply($user));

        $usageLimit = $service->incrementUsage($user, 'message_reply');

        $this->assertSame(2, $usageLimit->message_reply_used);
        $this->assertFalse($service->canSendMessageReply($user));
        $this->assertDatabaseHas('usage_logs', [
            'user_id' => $user->id,
            'type' => 'message_reply',
            'amount' => 1,
        ]);
    }

    public function test_it_blocks_usage_that_exceeds_limit(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create(['message_reply_limit' => 1]);
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);
        UsageLimit::factory()->create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'message_reply_limit' => 1,
            'message_reply_used' => 1,
        ]);

        try {
            app(UsageLimitService::class)->incrementUsage($user, 'message_reply');
            $this->fail('Usage increment should have been blocked.');
        } catch (InvalidArgumentException) {
            $this->assertTrue(true);
        }

        $this->assertDatabaseHas('usage_logs', [
            'user_id' => $user->id,
            'type' => 'failed_reply',
        ]);
    }

    public function test_it_resets_expired_usage_cycle(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create(['message_reply_limit' => 10]);
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);
        UsageLimit::factory()->create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'message_reply_used' => 9,
            'comment_reply_used' => 8,
            'ai_reply_used' => 7,
            'reset_at' => now()->subDay(),
        ]);

        $usageLimit = app(UsageLimitService::class)->currentUsageLimit($user);

        $this->assertSame(0, $usageLimit->message_reply_used);
        $this->assertSame(0, $usageLimit->comment_reply_used);
        $this->assertSame(0, $usageLimit->ai_reply_used);
        $this->assertTrue($usageLimit->reset_at->isFuture());
    }
}
