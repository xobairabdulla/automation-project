<?php

namespace Tests\Feature\Admin;

use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private function makeSuperAdmin(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $role = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);
        $user->roles()->attach($role);

        return $user;
    }

    public function test_non_admin_cannot_access_admin_api(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $role = Role::firstOrCreate(['slug' => 'client-admin'], ['name' => 'Client Admin']);
        $user->roles()->attach($role);
        Sanctum::actingAs($user);

        $this->getJson('/api/admin/dashboard')->assertForbidden();
    }

    public function test_super_admin_can_view_dashboard_stats(): void
    {
        $admin = $this->makeSuperAdmin();
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.total_users', 1);
    }

    public function test_super_admin_can_list_users(): void
    {
        $admin = $this->makeSuperAdmin();
        User::factory()->count(3)->create();
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/users')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_super_admin_can_suspend_and_activate_user(): void
    {
        $admin = $this->makeSuperAdmin();
        $target = User::factory()->create(['status' => 'active']);
        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/users/{$target->id}/suspend")
            ->assertOk();

        $this->assertDatabaseHas('users', ['id' => $target->id, 'status' => 'suspended']);

        $this->postJson("/api/admin/users/{$target->id}/activate")
            ->assertOk();

        $this->assertDatabaseHas('users', ['id' => $target->id, 'status' => 'active']);
    }

    public function test_super_admin_can_assign_plan_to_user(): void
    {
        $admin = $this->makeSuperAdmin();
        $target = User::factory()->create();
        $plan = Plan::factory()->create(['status' => 'active']);
        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/users/{$target->id}/assign-plan", [
            'plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
        ])->assertOk();

        $this->assertDatabaseHas('subscriptions', ['user_id' => $target->id, 'plan_id' => $plan->id]);
    }

    public function test_super_admin_can_extend_user_limits(): void
    {
        $admin = $this->makeSuperAdmin();
        $target = User::factory()->create();
        $plan = Plan::factory()->create(['message_reply_limit' => 100]);
        $subscription = Subscription::factory()->create(['user_id' => $target->id, 'plan_id' => $plan->id, 'status' => 'active']);
        $subscription->usageLimit()->create([
            'tenant_id' => $target->tenant_id,
            'user_id' => $target->id,
            'message_reply_limit' => 100,
            'message_reply_used' => 0,
            'comment_reply_limit' => 100,
            'comment_reply_used' => 0,
            'ai_reply_limit' => 50,
            'ai_reply_used' => 0,
            'connected_page_limit' => 1,
            'team_member_limit' => 2,
            'knowledge_base_limit' => 25,
            'reset_at' => now()->addMonth(),
        ]);
        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/users/{$target->id}/extend-limit", [
            'message_reply_extra' => 500,
            'ai_reply_extra' => 100,
        ])->assertOk();

        $this->assertDatabaseHas('usage_limits', [
            'user_id' => $target->id,
            'message_reply_limit' => 600,
            'ai_reply_limit' => 150,
        ]);
    }

    public function test_super_admin_can_create_and_update_plan(): void
    {
        $admin = $this->makeSuperAdmin();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/plans', [
            'name' => 'Enterprise',
            'monthly_price' => 299,
            'yearly_price' => 2990,
            'message_reply_limit' => 100000,
            'comment_reply_limit' => 100000,
            'ai_reply_limit' => 20000,
            'connected_page_limit' => 100,
            'team_member_limit' => 200,
            'knowledge_base_limit' => 5000,
            'status' => 'active',
        ])->assertCreated();

        $planId = $response->json('data.id');

        $this->putJson("/api/admin/plans/{$planId}", ['name' => 'Enterprise Plus'])
            ->assertOk();

        $this->assertDatabaseHas('plans', ['id' => $planId, 'name' => 'Enterprise Plus']);
    }

    public function test_audit_logs_are_written_for_admin_actions(): void
    {
        $admin = $this->makeSuperAdmin();
        $target = User::factory()->create(['status' => 'active']);
        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/users/{$target->id}/suspend")->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'user.suspended',
            'entity_type' => 'User',
            'entity_id' => $target->id,
        ]);
    }
}
