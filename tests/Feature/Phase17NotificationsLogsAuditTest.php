<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\EmailLog;
use App\Models\Role;
use App\Models\User;
use App\Notifications\FacebookTokenExpiredNotification;
use App\Notifications\PaymentFailedNotification;
use App\Notifications\PaymentSuccessNotification;
use App\Notifications\UsageLimitExceededNotification;
use App\Notifications\UsageLimitWarningNotification;
use App\Notifications\WebhookFailedNotification;
use App\Services\NotificationService;
use App\Services\UsageLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class Phase17NotificationsLogsAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
    }

    // --- Notification classes ---

    public function test_usage_limit_warning_notification_via_database(): void
    {
        Notification::fake();
        $n = new UsageLimitWarningNotification('message_reply', 80, 100);
        $this->assertContains('database', $n->via($this->owner));
    }

    public function test_usage_limit_warning_notification_data(): void
    {
        $n = new UsageLimitWarningNotification('message_reply', 80, 100);
        $data = $n->toArray($this->owner);
        $this->assertSame('usage_limit_warning', $data['type']);
        $this->assertSame(80, $data['used']);
        $this->assertSame(100, $data['limit']);
    }

    public function test_usage_limit_exceeded_notification_data(): void
    {
        $n = new UsageLimitExceededNotification('ai_reply');
        $data = $n->toArray($this->owner);
        $this->assertSame('usage_limit_exceeded', $data['type']);
        $this->assertSame('ai_reply', $data['limit_type']);
    }

    public function test_payment_failed_notification_data(): void
    {
        $n = new PaymentFailedNotification('card_declined');
        $data = $n->toArray($this->owner);
        $this->assertSame('payment_failed', $data['type']);
        $this->assertSame('card_declined', $data['reason']);
    }

    public function test_webhook_failed_notification_data(): void
    {
        $n = new WebhookFailedNotification('messages', 'timeout');
        $data = $n->toArray($this->owner);
        $this->assertSame('webhook_failed', $data['type']);
        $this->assertSame('messages', $data['event_type']);
    }

    public function test_facebook_token_expired_notification_data(): void
    {
        $n = new FacebookTokenExpiredNotification('My Page');
        $data = $n->toArray($this->owner);
        $this->assertSame('facebook_token_expired', $data['type']);
        $this->assertSame('My Page', $data['page_name']);
    }

    // --- NotificationService ---

    public function test_notification_service_send_delivers_database_notification(): void
    {
        Notification::fake();
        $service = app(NotificationService::class);
        $service->notifyUsageLimitWarning($this->owner, 'message_reply', 80, 100);
        Notification::assertSentTo($this->owner, UsageLimitWarningNotification::class);
    }

    public function test_notification_service_notify_exceeded(): void
    {
        Notification::fake();
        $service = app(NotificationService::class);
        $service->notifyUsageLimitExceeded($this->owner, 'comment_reply');
        Notification::assertSentTo($this->owner, UsageLimitExceededNotification::class);
    }

    public function test_notification_service_notify_payment_failed(): void
    {
        Notification::fake();
        $service = app(NotificationService::class);
        $service->notifyPaymentFailed($this->owner, 'insufficient_funds');
        Notification::assertSentTo($this->owner, PaymentFailedNotification::class);
    }

    // --- UsageLimitService triggers ---

    public function test_usage_limit_service_sends_exceeded_notification(): void
    {
        Notification::fake();
        $service = app(UsageLimitService::class);
        $limit = $service->currentUsageLimit($this->owner);
        $limit->update(['message_reply_limit' => 1, 'message_reply_used' => 0]);

        // Attempting to use 2 when limit is 1 should exceed
        try {
            $service->incrementUsage($this->owner, 'message_reply', 2);
        } catch (\InvalidArgumentException) {
        }

        Notification::assertSentTo($this->owner, UsageLimitExceededNotification::class);
    }

    public function test_usage_limit_service_sends_warning_at_80_percent(): void
    {
        Notification::fake();
        $service = app(UsageLimitService::class);
        $limit = $service->currentUsageLimit($this->owner);
        // Set limit=10, used=7 (70%) → after adding 2 becomes 9 (90%) → crosses 80% threshold
        $limit->update(['message_reply_limit' => 10, 'message_reply_used' => 7]);

        $service->incrementUsage($this->owner, 'message_reply', 2);

        Notification::assertSentTo($this->owner, UsageLimitWarningNotification::class);
    }

    // --- EmailLog model ---

    public function test_email_log_record_creates_entry(): void
    {
        EmailLog::record('test@example.com', 'Hello', 'sent', $this->owner->id, $this->owner->id);

        $this->assertDatabaseHas('email_logs', [
            'to_email' => 'test@example.com',
            'subject' => 'Hello',
            'status' => 'sent',
        ]);
    }

    public function test_email_log_record_failed_entry(): void
    {
        EmailLog::record('fail@example.com', 'Test Subject', 'failed', null, null, 'SMTP timeout');

        $this->assertDatabaseHas('email_logs', [
            'to_email' => 'fail@example.com',
            'status' => 'failed',
            'error_message' => 'SMTP timeout',
        ]);
    }

    public function test_team_invite_logs_email(): void
    {
        Notification::fake();
        $service = app(\App\Services\TeamService::class);
        $service->invite($this->owner, 'member@example.com', 'agent');

        $this->assertDatabaseHas('email_logs', [
            'to_email' => 'member@example.com',
        ]);
    }

    // --- Notifications page ---

    public function test_notifications_page_renders(): void
    {
        $response = $this->actingAs($this->owner)->get('/notifications');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('client/notifications'));
    }

    public function test_mark_read_endpoint(): void
    {
        $this->owner->notify(new UsageLimitWarningNotification('message_reply', 80, 100));
        $notification = $this->owner->notifications()->first();

        $response = $this->actingAs($this->owner)->postJson("/notifications/{$notification->id}/read");
        $response->assertOk();
        $this->assertNotNull($this->owner->notifications()->first()->read_at);
    }

    public function test_mark_all_read_endpoint(): void
    {
        $this->owner->notify(new UsageLimitWarningNotification('message_reply', 80, 100));
        $this->owner->notify(new PaymentFailedNotification());

        $response = $this->actingAs($this->owner)->postJson('/notifications/read-all');
        $response->assertOk();
        $this->assertSame(0, $this->owner->unreadNotifications()->count());
    }

    public function test_delete_notification_endpoint(): void
    {
        $this->owner->notify(new UsageLimitWarningNotification('message_reply', 80, 100));
        $notification = $this->owner->notifications()->first();

        $response = $this->actingAs($this->owner)->deleteJson("/notifications/{$notification->id}");
        $response->assertOk();
        $this->assertSame(0, $this->owner->notifications()->count());
    }

    // --- Admin audit logs ---

    public function test_admin_audit_logs_page_renders(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']));

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'user.suspended',
            'entity_type' => 'User',
            'entity_id' => 1,
        ]);

        $response = $this->actingAs($admin)->get('/admin/audit-logs');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('admin/audit-logs'));
    }

    public function test_admin_audit_logs_requires_super_admin(): void
    {
        $response = $this->actingAs($this->owner)->get('/admin/audit-logs');
        $response->assertForbidden();
    }

    // --- Admin email logs ---

    public function test_admin_email_logs_page_renders(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']));

        EmailLog::factory()->count(3)->create();

        $response = $this->actingAs($admin)->get('/admin/email-logs');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('admin/email-logs'));
    }

    public function test_admin_email_logs_requires_super_admin(): void
    {
        $response = $this->actingAs($this->owner)->get('/admin/email-logs');
        $response->assertForbidden();
    }

    // --- Shared data unread count ---

    public function test_unread_notification_count_in_shared_data(): void
    {
        $this->owner->notify(new PaymentFailedNotification());

        $response = $this->actingAs($this->owner)->get('/notifications');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('unreadNotifications', 1));
    }
}
