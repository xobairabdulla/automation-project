<?php

namespace Tests\Feature;

use App\Models\AiReplyLog;
use App\Models\FacebookComment;
use App\Models\FacebookPage;
use App\Models\Payment;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase18FrontendUITest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']));
    }

    // --- Comments page ---

    public function test_comments_page_renders_for_authenticated_user(): void
    {
        $response = $this->actingAs($this->owner)->get('/comments');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('client/comments'));
    }

    public function test_comments_page_requires_auth(): void
    {
        $response = $this->get('/comments');
        $response->assertRedirect('/login');
    }

    public function test_comments_page_shows_tenant_comments_only(): void
    {
        $fbPage = FacebookPage::factory()->create(['user_id' => $this->owner->id]);
        FacebookComment::factory()->count(3)->create(['tenant_id' => $this->owner->id, 'facebook_page_id' => $fbPage->id]);

        $other = User::factory()->create();
        FacebookComment::factory()->count(2)->create(['tenant_id' => $other->id]);

        $response = $this->actingAs($this->owner)->get('/comments');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('comments.total', 3));
    }

    public function test_comments_page_filters_by_status(): void
    {
        $fbPage = FacebookPage::factory()->create(['user_id' => $this->owner->id]);
        FacebookComment::factory()->create(['tenant_id' => $this->owner->id, 'status' => 'pending', 'facebook_page_id' => $fbPage->id]);
        FacebookComment::factory()->create(['tenant_id' => $this->owner->id, 'status' => 'replied', 'facebook_page_id' => $fbPage->id]);

        $response = $this->actingAs($this->owner)->get('/comments?status=pending');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('comments.total', 1));
    }

    public function test_comments_page_passes_pages_list(): void
    {
        FacebookPage::factory()->count(2)->create(['user_id' => $this->owner->id]);

        $response = $this->actingAs($this->owner)->get('/comments');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('pages', 2));
    }

    // --- Admin Payments page ---

    public function test_admin_payments_page_renders(): void
    {
        Payment::factory()->count(3)->create();

        $response = $this->actingAs($this->admin)->get('/admin/payments');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('admin/payments'));
    }

    public function test_admin_payments_requires_super_admin(): void
    {
        $response = $this->actingAs($this->owner)->get('/admin/payments');
        $response->assertForbidden();
    }

    public function test_admin_payments_has_stats(): void
    {
        Payment::factory()->count(2)->create(['status' => 'completed', 'amount' => 10.00]);
        Payment::factory()->create(['status' => 'failed']);

        $response = $this->actingAs($this->admin)->get('/admin/payments');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('stats')
            ->where('stats.completed', 2)
            ->where('stats.failed', 1)
        );
    }

    public function test_admin_payments_filters_by_status(): void
    {
        Payment::factory()->count(2)->create(['status' => 'completed']);
        Payment::factory()->create(['status' => 'failed']);

        $response = $this->actingAs($this->admin)->get('/admin/payments?status=failed');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('payments.total', 1));
    }

    // --- Admin AI Logs page ---

    public function test_admin_ai_logs_page_renders(): void
    {
        AiReplyLog::factory()->count(2)->create([
            'tenant_id' => $this->owner->id,
            'model' => 'gpt-4',
            'status' => 'success',
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/ai-logs');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('admin/ai-logs'));
    }

    public function test_admin_ai_logs_requires_super_admin(): void
    {
        $response = $this->actingAs($this->owner)->get('/admin/ai-logs');
        $response->assertForbidden();
    }

    public function test_admin_ai_logs_has_stats(): void
    {
        AiReplyLog::factory()->count(2)->create(['tenant_id' => $this->owner->id, 'status' => 'success', 'tokens_used' => 100]);
        AiReplyLog::factory()->create(['tenant_id' => $this->owner->id, 'status' => 'failed', 'tokens_used' => 0]);

        $response = $this->actingAs($this->admin)->get('/admin/ai-logs');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('stats.total', 3)
            ->where('stats.success', 2)
            ->where('stats.failed', 1)
            ->where('stats.total_tokens', 200)
        );
    }

    public function test_admin_ai_logs_filters_by_status(): void
    {
        AiReplyLog::factory()->count(3)->create(['tenant_id' => $this->owner->id, 'status' => 'success']);
        AiReplyLog::factory()->create(['tenant_id' => $this->owner->id, 'status' => 'failed']);

        $response = $this->actingAs($this->admin)->get('/admin/ai-logs?status=failed');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('logs.total', 1));
    }

    // --- Admin System Settings page ---

    public function test_admin_system_settings_page_renders(): void
    {
        SystemSetting::factory()->count(3)->create();

        $response = $this->actingAs($this->admin)->get('/admin/system-settings');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/system-settings')
            ->has('settings', 3)
        );
    }

    public function test_admin_system_settings_requires_super_admin(): void
    {
        $response = $this->actingAs($this->owner)->get('/admin/system-settings');
        $response->assertForbidden();
    }

    public function test_admin_system_settings_upsert_creates_setting(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/system-settings', [
            'key' => 'site_name',
            'value' => 'My App',
            'type' => 'string',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('system_settings', ['key' => 'site_name', 'value_encrypted_or_json' => 'My App']);
    }

    public function test_admin_system_settings_upsert_updates_existing(): void
    {
        SystemSetting::factory()->create(['key' => 'site_name', 'value_encrypted_or_json' => 'Old']);

        $this->actingAs($this->admin)->post('/admin/system-settings', [
            'key' => 'site_name',
            'value' => 'New',
            'type' => 'string',
        ]);

        $this->assertDatabaseHas('system_settings', ['key' => 'site_name', 'value_encrypted_or_json' => 'New']);
        $this->assertDatabaseCount('system_settings', 1);
    }

    public function test_admin_system_settings_delete(): void
    {
        $setting = SystemSetting::factory()->create();

        $response = $this->actingAs($this->admin)->delete("/admin/system-settings/{$setting->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('system_settings', ['id' => $setting->id]);
    }

    public function test_admin_system_settings_upsert_requires_super_admin(): void
    {
        $response = $this->actingAs($this->owner)->post('/admin/system-settings', [
            'key' => 'test',
            'value' => 'test',
            'type' => 'string',
        ]);

        $response->assertForbidden();
    }

    public function test_admin_system_settings_delete_requires_super_admin(): void
    {
        $setting = SystemSetting::factory()->create();

        $response = $this->actingAs($this->owner)->delete("/admin/system-settings/{$setting->id}");

        $response->assertForbidden();
    }
}
