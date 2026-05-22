<?php

namespace Tests\Feature;

use App\Models\AiProviderSetting;
use App\Models\FacebookAccount;
use App\Models\FacebookComment;
use App\Models\FacebookPage;
use App\Models\Payment;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase19SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->other = User::factory()->create();
    }

    // --- Tenant isolation ---

    public function test_comments_only_returns_own_tenant_data(): void
    {
        $ownerPage = FacebookPage::factory()->create(['user_id' => $this->owner->id]);
        FacebookComment::factory()->count(3)->create(['tenant_id' => $this->owner->id, 'facebook_page_id' => $ownerPage->id]);

        $otherPage = FacebookPage::factory()->create(['user_id' => $this->other->id]);
        FacebookComment::factory()->count(5)->create(['tenant_id' => $this->other->id, 'facebook_page_id' => $otherPage->id]);

        $response = $this->actingAs($this->owner)->get('/comments');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('comments.total', 3));
    }

    public function test_payments_admin_only(): void
    {
        $response = $this->actingAs($this->owner)->get('/admin/payments');
        $response->assertForbidden();
    }

    public function test_ai_logs_admin_only(): void
    {
        $response = $this->actingAs($this->owner)->get('/admin/ai-logs');
        $response->assertForbidden();
    }

    public function test_system_settings_admin_only(): void
    {
        $response = $this->actingAs($this->owner)->get('/admin/system-settings');
        $response->assertForbidden();
    }

    public function test_audit_logs_admin_only(): void
    {
        $response = $this->actingAs($this->owner)->get('/admin/audit-logs');
        $response->assertForbidden();
    }

    public function test_unauthenticated_cannot_access_protected_routes(): void
    {
        $this->get('/comments')->assertRedirect('/login');
        $this->get('/notifications')->assertRedirect('/login');
        $this->get('/analytics')->assertRedirect('/login');
    }

    // --- Sensitive field hiding ---

    public function test_facebook_page_token_hidden_from_serialization(): void
    {
        FacebookPage::factory()->create([
            'user_id' => $this->owner->id,
            'page_access_token_encrypted' => 'super_secret_token',
        ]);

        $response = $this->actingAs($this->owner)->getJson('/api/facebook/pages');
        $response->assertOk();

        $content = $response->json();
        $this->assertStringNotContainsString('super_secret_token', json_encode($content));

        foreach ($content['data'] ?? $content as $page) {
            $this->assertArrayNotHasKey('page_access_token_encrypted', $page);
        }
    }

    public function test_ai_provider_key_hidden_from_serialization(): void
    {
        $setting = AiProviderSetting::create([
            'provider_name' => 'openai',
            'api_key_encrypted' => 'sk-secret-key-12345',
            'model' => 'gpt-4',
            'status' => 'active',
        ]);

        $serialized = $setting->toArray();
        $this->assertArrayNotHasKey('api_key_encrypted', $serialized);
    }

    public function test_facebook_account_token_hidden_from_serialization(): void
    {
        $account = FacebookAccount::factory()->create([
            'user_id' => $this->owner->id,
            'access_token_encrypted' => 'fb_user_token_secret',
        ]);

        $serialized = $account->toArray();
        $this->assertArrayNotHasKey('access_token_encrypted', $serialized);
    }

    public function test_sensitive_system_setting_value_masked(): void
    {
        $setting = SystemSetting::create([
            'key' => 'openai_api_key',
            'value_encrypted_or_json' => 'sk-real-secret-key',
            'type' => 'string',
            'is_sensitive' => true,
        ]);

        $this->assertSame('••••••••', $setting->value_encrypted_or_json);
    }

    public function test_non_sensitive_system_setting_value_visible(): void
    {
        $setting = SystemSetting::create([
            'key' => 'site_name',
            'value_encrypted_or_json' => 'My App',
            'type' => 'string',
            'is_sensitive' => false,
        ]);

        $this->assertSame('My App', $setting->value_encrypted_or_json);
    }

    // --- Facebook webhook signature verification ---

    public function test_facebook_webhook_rejects_invalid_signature(): void
    {
        config(['services.meta.app_secret' => 'test_secret']);

        $payload = json_encode(['object' => 'page', 'entry' => []]);
        $response = $this->postJson('/api/webhooks/facebook', json_decode($payload, true), [
            'X-Hub-Signature-256' => 'sha256=invalidsignature',
        ]);

        $response->assertStatus(403);
    }

    public function test_facebook_webhook_accepts_valid_signature(): void
    {
        config(['services.meta.app_secret' => 'test_secret']);

        $payload = json_encode(['object' => 'page', 'entry' => []]);
        $signature = 'sha256='.hash_hmac('sha256', $payload, 'test_secret');

        $response = $this->call('POST', '/api/webhooks/facebook', [], [], [], [
            'HTTP_X-Hub-Signature-256' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $response->assertStatus(200);
    }

    // --- Stripe webhook signature verification ---

    public function test_stripe_webhook_rejects_missing_signature(): void
    {
        $response = $this->postJson('/api/webhooks/stripe', ['type' => 'checkout.session.completed']);
        $response->assertStatus(400);
    }

    // --- Rate limiting (structural check) ---

    public function test_api_auth_routes_have_throttle_middleware(): void
    {
        $router = app(\Illuminate\Routing\Router::class);

        $loginRoute = collect($router->getRoutes())->first(fn ($r) => $r->getName() === 'api.auth.login');
        $this->assertNotNull($loginRoute, 'Login route must exist');

        $middlewares = $loginRoute->middleware();
        $hasThrottle = collect($middlewares)->contains(fn ($m) => str_starts_with($m, 'throttle:'));
        $this->assertTrue($hasThrottle, 'Login route must have throttle middleware');
    }

    public function test_webhook_routes_have_throttle_middleware(): void
    {
        $router = app(\Illuminate\Routing\Router::class);

        $webhookRoute = collect($router->getRoutes())->first(fn ($r) => $r->getName() === 'api.webhooks.facebook.receive');
        $this->assertNotNull($webhookRoute, 'Facebook webhook route must exist');

        $middlewares = $webhookRoute->middleware();
        $hasThrottle = collect($middlewares)->contains(fn ($m) => str_starts_with($m, 'throttle:'));
        $this->assertTrue($hasThrottle, 'Facebook webhook route must have throttle middleware');
    }

    // --- CORS config exists ---

    public function test_cors_config_exists(): void
    {
        $this->assertNotNull(config('cors'));
        $this->assertIsArray(config('cors.paths'));
        $this->assertContains('api/*', config('cors.paths'));
    }

    // --- Payment isolation (users see own payments only) ---

    public function test_billing_page_shows_own_payments_only(): void
    {
        $role = Role::firstOrCreate(['slug' => 'client-admin'], ['name' => 'Client Admin']);
        $this->owner->roles()->attach($role);

        Payment::factory()->count(2)->create(['user_id' => $this->owner->id]);
        Payment::factory()->count(3)->create(['user_id' => $this->other->id]);

        $response = $this->actingAs($this->owner)->get('/billing');
        $response->assertOk();

        $response->assertInertia(fn ($page) => $page->has('payments', 2));
    }
}
