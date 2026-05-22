<?php

namespace Tests\Feature\Client;

use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\UsageLimit;
use App\Models\UsageLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function makeClientUser(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $role = Role::firstOrCreate(['slug' => 'client-admin'], ['name' => 'Client Admin']);
        $user->roles()->attach($role);

        return $user;
    }

    public function test_unauthenticated_user_cannot_access_dashboard_api(): void
    {
        $this->getJson('/api/dashboard')->assertUnauthorized();
    }

    public function test_dashboard_api_returns_subscription_usage_and_stats(): void
    {
        $user = $this->makeClientUser();
        $plan = Plan::factory()->create(['name' => 'Growth', 'message_reply_limit' => 1000]);
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);
        UsageLimit::factory()->create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'message_reply_limit' => 1000,
            'message_reply_used' => 50,
            'comment_reply_limit' => 500,
            'comment_reply_used' => 0,
            'ai_reply_limit' => 100,
            'ai_reply_used' => 0,
        ]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/dashboard')->assertOk();

        $response->assertJsonPath('data.subscription.plan.name', 'Growth');
        $response->assertJsonPath('data.usage.message_reply_used', 50);
        $response->assertJsonPath('data.stats.messages_today', 0);
    }

    public function test_dashboard_stats_count_todays_usage_logs(): void
    {
        $user = $this->makeClientUser();
        Sanctum::actingAs($user);

        UsageLog::factory()->create(['user_id' => $user->id, 'type' => 'message_reply', 'amount' => 3]);
        UsageLog::factory()->create(['user_id' => $user->id, 'type' => 'message_reply', 'amount' => 2]);
        UsageLog::factory()->create(['user_id' => $user->id, 'type' => 'comment_reply', 'amount' => 5]);

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.stats.messages_today', 5)
            ->assertJsonPath('data.stats.comments_today', 5);
    }

    public function test_alerts_api_returns_warning_when_usage_above_80_percent(): void
    {
        $user = $this->makeClientUser();
        $plan = Plan::factory()->create(['message_reply_limit' => 100]);
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);
        UsageLimit::factory()->create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'message_reply_limit' => 100,
            'message_reply_used' => 85,
            'comment_reply_limit' => 100,
            'comment_reply_used' => 0,
            'ai_reply_limit' => 100,
            'ai_reply_used' => 0,
        ]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/dashboard/alerts')->assertOk();

        $alerts = $response->json('data');
        $types = array_column($alerts, 'type');
        $this->assertContains('warning', $types);
    }

    public function test_recent_activity_api_returns_usage_logs(): void
    {
        $user = $this->makeClientUser();
        UsageLog::factory()->count(5)->create(['user_id' => $user->id, 'type' => 'message_reply']);
        Sanctum::actingAs($user);

        $this->getJson('/api/dashboard/recent-activity')
            ->assertOk()
            ->assertJsonCount(5, 'data');
    }

    public function test_suspended_user_cannot_access_dashboard(): void
    {
        $user = User::factory()->create(['status' => 'suspended']);
        Sanctum::actingAs($user);

        $this->getJson('/api/dashboard')->assertForbidden();
    }
}
