<?php

namespace Tests\Feature\Facebook;

use App\Jobs\ProcessFacebookWebhookJob;
use App\Models\FacebookAccount;
use App\Models\FacebookPage;
use App\Models\Role;
use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class FacebookWebhookTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $role = Role::firstOrCreate(['slug' => 'client-admin'], ['name' => 'Client Admin']);
        $user->roles()->attach($role);

        return $user;
    }

    private function makePage(User $user, array $attrs = []): FacebookPage
    {
        $account = FacebookAccount::create([
            'user_id' => $user->id,
            'facebook_user_id' => 'fb_user_'.uniqid(),
            'name' => 'Test FB User',
            'access_token_encrypted' => 'fake_user_token',
        ]);

        return FacebookPage::create(array_merge([
            'user_id' => $user->id,
            'facebook_account_id' => $account->id,
            'page_id' => 'page_'.uniqid(),
            'page_name' => 'Test Page',
            'page_access_token_encrypted' => 'fake_page_token',
            'is_connected' => true,
            'automation_enabled' => true,
            'message_automation_enabled' => true,
            'comment_automation_enabled' => true,
        ], $attrs));
    }

    public function test_webhook_verify_challenge_with_valid_token(): void
    {
        config(['services.meta.webhook_verify_token' => 'test_verify_token']);

        $response = $this->get('/api/webhooks/facebook?' . http_build_query([
            'hub_mode' => 'subscribe',
            'hub_verify_token' => 'test_verify_token',
            'hub_challenge' => 'challenge_abc123',
        ]));

        $response->assertOk()->assertSee('challenge_abc123');
    }

    public function test_webhook_verify_challenge_with_invalid_token(): void
    {
        config(['services.meta.webhook_verify_token' => 'test_verify_token']);

        $response = $this->get('/api/webhooks/facebook?' . http_build_query([
            'hub_mode' => 'subscribe',
            'hub_verify_token' => 'wrong_token',
            'hub_challenge' => 'challenge_abc123',
        ]));

        $response->assertStatus(403);
    }

    public function test_webhook_receive_message_event_saves_and_queues(): void
    {
        Queue::fake();
        config(['services.meta.app_secret' => null]);

        $user = $this->makeUser();
        $page = $this->makePage($user);

        $payload = [
            'object' => 'page',
            'entry' => [
                [
                    'id' => $page->page_id,
                    'time' => time(),
                    'messaging' => [
                        [
                            'sender' => ['id' => 'sender_123'],
                            'recipient' => ['id' => $page->page_id],
                            'timestamp' => time() * 1000,
                            'message' => [
                                'mid' => 'mid.test.' . uniqid(),
                                'text' => 'Hello!',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/webhooks/facebook', $payload);

        $response->assertOk();

        $this->assertDatabaseCount('webhook_events', 1);
        $this->assertDatabaseHas('webhook_events', [
            'tenant_id' => $user->id,
            'facebook_page_id' => $page->id,
            'event_type' => 'message',
            'status' => 'queued',
        ]);

        Queue::assertPushed(ProcessFacebookWebhookJob::class);
    }

    public function test_webhook_ignores_unknown_page(): void
    {
        Queue::fake();
        config(['services.meta.app_secret' => null]);

        $payload = [
            'object' => 'page',
            'entry' => [
                [
                    'id' => 'unknown_page_id_99999',
                    'messaging' => [
                        [
                            'sender' => ['id' => 'sender_123'],
                            'recipient' => ['id' => 'unknown_page_id_99999'],
                            'message' => ['mid' => 'mid.123', 'text' => 'Hi'],
                        ],
                    ],
                ],
            ],
        ];

        $this->postJson('/api/webhooks/facebook', $payload)->assertOk();

        $this->assertDatabaseCount('webhook_events', 0);
        Queue::assertNothingPushed();
    }

    public function test_webhook_deduplicates_by_external_event_id(): void
    {
        Queue::fake();
        config(['services.meta.app_secret' => null]);

        $user = $this->makeUser();
        $page = $this->makePage($user);
        $mid = 'mid.test.duplicate123';

        $payload = fn () => [
            'object' => 'page',
            'entry' => [
                [
                    'id' => $page->page_id,
                    'messaging' => [
                        [
                            'sender' => ['id' => 'sender_123'],
                            'recipient' => ['id' => $page->page_id],
                            'message' => ['mid' => $mid, 'text' => 'Hello'],
                        ],
                    ],
                ],
            ],
        ];

        $this->postJson('/api/webhooks/facebook', $payload())->assertOk();
        $this->postJson('/api/webhooks/facebook', $payload())->assertOk();

        $this->assertDatabaseCount('webhook_events', 1);
        Queue::assertPushed(ProcessFacebookWebhookJob::class, 1);
    }

    public function test_webhook_ignores_non_page_object(): void
    {
        Queue::fake();
        config(['services.meta.app_secret' => null]);

        $this->postJson('/api/webhooks/facebook', ['object' => 'user', 'entry' => []])->assertOk();

        $this->assertDatabaseCount('webhook_events', 0);
        Queue::assertNothingPushed();
    }

    public function test_webhook_updates_page_last_webhook_received_at(): void
    {
        Queue::fake();
        config(['services.meta.app_secret' => null]);

        $user = $this->makeUser();
        $page = $this->makePage($user);

        $this->assertNull($page->last_webhook_received_at);

        $payload = [
            'object' => 'page',
            'entry' => [
                [
                    'id' => $page->page_id,
                    'messaging' => [
                        [
                            'sender' => ['id' => 's1'],
                            'recipient' => ['id' => $page->page_id],
                            'message' => ['mid' => 'mid.x', 'text' => 'hi'],
                        ],
                    ],
                ],
            ],
        ];

        $this->postJson('/api/webhooks/facebook', $payload)->assertOk();

        $this->assertNotNull($page->fresh()->last_webhook_received_at);
    }

    public function test_admin_can_view_webhook_logs(): void
    {
        $admin = $this->makeUser();
        $adminRole = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);
        $admin->roles()->attach($adminRole);

        $this->actingAs($admin);
        $this->get('/admin/webhook-logs')->assertOk();
    }

    public function test_admin_can_view_webhook_event_detail(): void
    {
        $admin = $this->makeUser();
        $adminRole = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);
        $admin->roles()->attach($adminRole);

        $user = $this->makeUser();
        $page = $this->makePage($user);

        $event = WebhookEvent::create([
            'tenant_id' => $user->id,
            'facebook_page_id' => $page->id,
            'event_type' => 'message',
            'payload_json' => ['test' => true],
            'status' => 'completed',
        ]);

        $this->actingAs($admin);
        $this->get("/admin/webhook-logs/{$event->id}")->assertOk();
    }

    public function test_admin_can_retry_failed_event(): void
    {
        Queue::fake();

        $admin = $this->makeUser();
        $adminRole = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);
        $admin->roles()->attach($adminRole);

        $user = $this->makeUser();
        $page = $this->makePage($user);

        $event = WebhookEvent::create([
            'tenant_id' => $user->id,
            'facebook_page_id' => $page->id,
            'event_type' => 'message',
            'payload_json' => ['test' => true],
            'status' => 'failed',
            'error_message' => 'Something broke',
        ]);

        $this->actingAs($admin);
        $this->post("/admin/webhook-logs/{$event->id}/retry")->assertRedirect();

        $this->assertDatabaseHas('webhook_events', ['id' => $event->id, 'status' => 'queued']);
        Queue::assertPushed(ProcessFacebookWebhookJob::class);
    }
}
