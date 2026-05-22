<?php

namespace Tests\Feature;

use App\Models\LimitExtensionPackage;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\UsageLimit;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\UsageLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase14PaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Plan $plan;

    private Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->plan = Plan::factory()->create([
            'name' => 'Pro',
            'monthly_price' => 29.99,
            'yearly_price' => 299.99,
            'stripe_monthly_price_id' => 'price_monthly_test',
            'stripe_yearly_price_id' => 'price_yearly_test',
            'message_reply_limit' => 1000,
            'comment_reply_limit' => 1000,
            'ai_reply_limit' => 500,
            'status' => 'active',
        ]);
        $this->subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'plan_id' => $this->plan->id,
            'status' => 'active',
        ]);
        UsageLimit::factory()->create([
            'user_id' => $this->user->id,
            'subscription_id' => $this->subscription->id,
            'message_reply_limit' => 1000,
            'message_reply_used' => 0,
            'comment_reply_limit' => 1000,
            'comment_reply_used' => 0,
            'ai_reply_limit' => 500,
            'ai_reply_used' => 0,
            'reset_at' => now()->addMonth(),
        ]);
    }

    // --- Billing page ---

    public function test_billing_page_renders(): void
    {
        $response = $this->actingAs($this->user)->get('/billing');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('client/billing'));
    }

    public function test_billing_page_contains_subscription_and_plans(): void
    {
        $response = $this->actingAs($this->user)->get('/billing');
        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->has('subscription')
                ->has('plans')
                ->has('packages')
                ->has('payments')
        );
    }

    // --- Payment model ---

    public function test_payment_can_be_created(): void
    {
        $payment = Payment::factory()->create(['user_id' => $this->user->id]);
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'user_id' => $this->user->id]);
    }

    public function test_payment_belongs_to_user(): void
    {
        $payment = Payment::factory()->create(['user_id' => $this->user->id]);
        $this->assertSame($this->user->id, $payment->user->id);
    }

    // --- LimitExtensionPackage model ---

    public function test_limit_extension_package_can_be_created(): void
    {
        $pkg = LimitExtensionPackage::factory()->create(['name' => 'Message Boost 500', 'message_extra' => 500]);
        $this->assertDatabaseHas('limit_extension_packages', ['name' => 'Message Boost 500', 'message_extra' => 500]);
    }

    // --- Admin assign plan (without payment) ---

    public function test_admin_can_assign_plan_to_user_without_payment(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->create(['name' => 'Super Admin', 'slug' => 'super-admin']);

        $newPlan = Plan::factory()->create(['name' => 'Enterprise', 'status' => 'active']);

        $response = $this->actingAs($admin)->post("/admin/users/{$this->user->id}/assign-plan", [
            'plan_id' => $newPlan->id,
            'billing_cycle' => 'monthly',
        ]);

        $response->assertRedirect();

        // New subscription created and active
        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $this->user->id,
            'plan_id' => $newPlan->id,
            'status' => 'active',
        ]);

        // Old subscription expired
        $this->assertDatabaseHas('subscriptions', [
            'id' => $this->subscription->id,
            'status' => 'expired',
        ]);

        // UsageLimit created for new subscription
        $newSub = $this->user->subscriptions()->where('plan_id', $newPlan->id)->first();
        $this->assertNotNull($newSub);
        $this->assertDatabaseHas('usage_limits', ['subscription_id' => $newSub->id]);

        // Admin-granted payment record created
        $this->assertDatabaseHas('payments', [
            'user_id' => $this->user->id,
            'type' => 'admin_granted',
            'status' => 'completed',
            'amount' => 0,
        ]);
    }

    public function test_non_admin_cannot_assign_plan(): void
    {
        $other = User::factory()->create();
        $newPlan = Plan::factory()->create(['status' => 'active']);

        $response = $this->actingAs($other)->post("/admin/users/{$this->user->id}/assign-plan", [
            'plan_id' => $newPlan->id,
            'billing_cycle' => 'monthly',
        ]);

        $response->assertForbidden();
    }

    // --- PaymentService: recordAdminGrantedSubscription ---

    public function test_record_admin_granted_subscription_creates_payment(): void
    {
        $admin = User::factory()->create();
        $service = app(PaymentService::class);

        $service->recordAdminGrantedSubscription($admin, $this->user, $this->subscription);

        $this->assertDatabaseHas('payments', [
            'user_id' => $this->user->id,
            'subscription_id' => $this->subscription->id,
            'type' => 'admin_granted',
            'status' => 'completed',
            'amount' => 0,
        ]);
    }

    // --- PaymentService: fulfillExtensionPayment ---

    public function test_fulfill_extension_payment_increments_usage_limits(): void
    {
        $pkg = LimitExtensionPackage::factory()->create([
            'message_extra' => 250,
            'comment_extra' => 250,
            'ai_extra' => 100,
        ]);

        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'pending',
            'type' => 'limit_extension',
            'metadata_json' => ['package_id' => $pkg->id],
        ]);

        $service = app(PaymentService::class);
        $service->fulfillExtensionPayment($payment, app(UsageLimitService::class));

        $payment->refresh();
        $this->assertSame('completed', $payment->status);
        $this->assertNotNull($payment->paid_at);

        $usageLimit = $this->user->usageLimits()->latest()->first();
        $this->assertSame(1250, $usageLimit->message_reply_limit);
        $this->assertSame(1250, $usageLimit->comment_reply_limit);
        $this->assertSame(600, $usageLimit->ai_reply_limit);
    }

    // --- Stripe webhook signature validation ---

    public function test_webhook_rejects_invalid_signature(): void
    {
        $response = $this->postJson('/api/webhooks/stripe', [], ['Stripe-Signature' => 'invalid']);
        $response->assertStatus(400);
    }

    public function test_webhook_rejects_missing_signature(): void
    {
        $response = $this->postJson('/api/webhooks/stripe', []);
        $response->assertStatus(400);
    }

    // --- Checkout errors without Stripe key ---

    public function test_checkout_fails_gracefully_without_stripe_price_id(): void
    {
        $planNoStripe = Plan::factory()->create([
            'status' => 'active',
            'stripe_monthly_price_id' => null,
        ]);

        $response = $this->actingAs($this->user)->post("/billing/checkout/{$planNoStripe->id}", [
            'billing_cycle' => 'monthly',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('payment');
    }

    // --- History endpoint ---

    public function test_payment_history_returns_paginated_list(): void
    {
        Payment::factory()->count(3)->create(['user_id' => $this->user->id, 'status' => 'completed']);

        $response = $this->actingAs($this->user)->getJson('/billing/history');
        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    }

    // --- UsageLimitService public method ---

    public function test_create_usage_limit_for_subscription(): void
    {
        $newSub = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'plan_id' => $this->plan->id,
            'status' => 'active',
        ]);

        $service = app(UsageLimitService::class);
        $limit = $service->createUsageLimitForSubscription($newSub);

        $this->assertDatabaseHas('usage_limits', [
            'subscription_id' => $newSub->id,
            'message_reply_limit' => $this->plan->message_reply_limit,
        ]);
    }
}
