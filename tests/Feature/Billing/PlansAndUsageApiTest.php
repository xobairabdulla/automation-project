<?php

namespace Tests\Feature\Billing;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\UsageLimit;
use App\Models\UsageLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlansAndUsageApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_plans_are_listed(): void
    {
        Plan::factory()->create(['name' => 'Starter', 'monthly_price' => 0, 'status' => 'active']);
        Plan::factory()->create(['name' => 'Hidden', 'monthly_price' => 99, 'status' => 'inactive']);

        $this->getJson('/api/plans')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Starter')
            ->assertJsonCount(1, 'data');
    }

    public function test_authenticated_user_can_view_subscription_usage_and_history(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $plan = Plan::factory()->create(['name' => 'Growth']);
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);
        UsageLimit::factory()->create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'message_reply_limit' => 100,
            'message_reply_used' => 81,
        ]);
        UsageLog::factory()->create([
            'user_id' => $user->id,
            'type' => 'message_reply',
            'amount' => 3,
        ]);

        $this->getJson('/api/user/subscription')
            ->assertOk()
            ->assertJsonPath('data.plan.name', 'Growth');

        $this->getJson('/api/user/usage')
            ->assertOk()
            ->assertJsonPath('data.message_reply_used', 81);

        $this->getJson('/api/user/usage/history')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'message_reply');
    }
}
