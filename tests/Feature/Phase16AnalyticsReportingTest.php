<?php

namespace Tests\Feature;

use App\Jobs\GenerateDailyAnalyticsJob;
use App\Jobs\GenerateMonthlyAnalyticsJob;
use App\Models\DailyUsageStat;
use App\Models\FacebookComment;
use App\Models\Message;
use App\Models\MonthlyUsageStat;
use App\Models\User;
use App\Services\AnalyticsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase16AnalyticsReportingTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
    }

    // --- AnalyticsService unit tests ---

    public function test_compute_daily_stats_counts_inbound_messages(): void
    {
        Message::factory()->count(3)->create([
            'tenant_id' => $this->owner->id,
            'direction' => 'inbound',
        ]);

        $service = app(AnalyticsService::class);
        $stats = $service->computeDailyStats($this->owner, Carbon::today());

        $this->assertSame(3, $stats['messages_received']);
    }

    public function test_compute_daily_stats_counts_outbound_messages_as_replies(): void
    {
        Message::factory()->count(2)->create([
            'tenant_id' => $this->owner->id,
            'direction' => 'outbound',
        ]);

        $service = app(AnalyticsService::class);
        $stats = $service->computeDailyStats($this->owner, Carbon::today());

        $this->assertSame(2, $stats['message_replies_sent']);
    }

    public function test_compute_daily_stats_counts_comments(): void
    {
        FacebookComment::factory()->count(4)->create([
            'tenant_id' => $this->owner->id,
        ]);

        $service = app(AnalyticsService::class);
        $stats = $service->computeDailyStats($this->owner, Carbon::today());

        $this->assertSame(4, $stats['comments_received']);
    }

    public function test_compute_daily_stats_excludes_other_tenant_data(): void
    {
        $other = User::factory()->create();
        Message::factory()->count(5)->create([
            'tenant_id' => $other->id,
            'direction' => 'inbound',
        ]);

        $service = app(AnalyticsService::class);
        $stats = $service->computeDailyStats($this->owner, Carbon::today());

        $this->assertSame(0, $stats['messages_received']);
    }

    public function test_compute_daily_stats_scopes_by_date(): void
    {
        Message::factory()->count(2)->create([
            'tenant_id' => $this->owner->id,
            'direction' => 'inbound',
            'created_at' => now()->subDays(3),
        ]);

        $service = app(AnalyticsService::class);
        $stats = $service->computeDailyStats($this->owner, Carbon::today());

        $this->assertSame(0, $stats['messages_received']);
    }

    public function test_compute_monthly_stats_aggregates_daily_rows(): void
    {
        $service = app(AnalyticsService::class);

        DailyUsageStat::factory()->create([
            'tenant_id' => $this->owner->id,
            'date' => now()->startOfMonth()->toDateString(),
            'messages_received' => 10,
            'comments_received' => 5,
            'message_replies_sent' => 8,
            'comment_replies_sent' => 3,
            'ai_replies_sent' => 6,
            'manual_replies_sent' => 5,
            'human_handovers' => 2,
            'failed_replies' => 1,
        ]);
        DailyUsageStat::factory()->create([
            'tenant_id' => $this->owner->id,
            'date' => now()->startOfMonth()->addDay()->toDateString(),
            'messages_received' => 20,
            'comments_received' => 10,
            'message_replies_sent' => 15,
            'comment_replies_sent' => 7,
            'ai_replies_sent' => 12,
            'manual_replies_sent' => 10,
            'human_handovers' => 3,
            'failed_replies' => 2,
        ]);

        $stats = $service->computeMonthlyStats($this->owner, now()->year, now()->month);

        $this->assertSame(30, $stats['messages_received']);
        $this->assertSame(15, $stats['comments_received']);
        $this->assertSame(23, $stats['message_replies_sent']);
    }

    public function test_upsert_daily_stat_creates_record(): void
    {
        $service = app(AnalyticsService::class);
        $date = Carbon::today();

        $service->upsertDailyStat($this->owner->id, $date, [
            'messages_received' => 10,
            'comments_received' => 5,
            'message_replies_sent' => 8,
            'comment_replies_sent' => 3,
            'ai_replies_sent' => 6,
            'manual_replies_sent' => 5,
            'human_handovers' => 1,
            'failed_replies' => 0,
        ]);

        $this->assertDatabaseHas('daily_usage_stats', [
            'tenant_id' => $this->owner->id,
            'date' => $date->toDateString(),
            'messages_received' => 10,
        ]);
    }

    public function test_upsert_daily_stat_updates_existing_record(): void
    {
        $service = app(AnalyticsService::class);
        $date = Carbon::today();

        DailyUsageStat::factory()->create([
            'tenant_id' => $this->owner->id,
            'date' => $date->toDateString(),
            'messages_received' => 5,
            'comments_received' => 0,
            'message_replies_sent' => 0,
            'comment_replies_sent' => 0,
            'ai_replies_sent' => 0,
            'manual_replies_sent' => 0,
            'human_handovers' => 0,
            'failed_replies' => 0,
        ]);

        $service->upsertDailyStat($this->owner->id, $date, [
            'messages_received' => 20,
            'comments_received' => 0,
            'message_replies_sent' => 0,
            'comment_replies_sent' => 0,
            'ai_replies_sent' => 0,
            'manual_replies_sent' => 0,
            'human_handovers' => 0,
            'failed_replies' => 0,
        ]);

        $this->assertSame(1, DailyUsageStat::where('tenant_id', $this->owner->id)->count());
        $this->assertDatabaseHas('daily_usage_stats', [
            'tenant_id' => $this->owner->id,
            'messages_received' => 20,
        ]);
    }

    public function test_upsert_monthly_stat_creates_record(): void
    {
        $service = app(AnalyticsService::class);

        $service->upsertMonthlyStat($this->owner->id, 2026, 5, [
            'messages_received' => 100,
            'comments_received' => 50,
            'message_replies_sent' => 80,
            'comment_replies_sent' => 40,
            'ai_replies_sent' => 60,
            'manual_replies_sent' => 60,
            'human_handovers' => 10,
            'failed_replies' => 5,
        ]);

        $this->assertDatabaseHas('monthly_usage_stats', [
            'tenant_id' => $this->owner->id,
            'year' => 2026,
            'month' => 5,
            'messages_received' => 100,
        ]);
    }

    public function test_client_stats_returns_totals_and_daily(): void
    {
        $service = app(AnalyticsService::class);
        $date = now()->toDateString();

        DailyUsageStat::factory()->create([
            'tenant_id' => $this->owner->id,
            'date' => $date,
            'messages_received' => 15,
            'comments_received' => 7,
            'message_replies_sent' => 10,
            'comment_replies_sent' => 5,
            'ai_replies_sent' => 8,
            'manual_replies_sent' => 7,
            'human_handovers' => 2,
            'failed_replies' => 1,
        ]);

        $result = $service->clientStats($this->owner, $date, $date);

        $this->assertArrayHasKey('totals', $result);
        $this->assertArrayHasKey('daily', $result);
        $this->assertSame(15, $result['totals']['messages_received']);
        $this->assertCount(1, $result['daily']);
    }

    public function test_client_stats_team_member_uses_owner_tenant(): void
    {
        $member = User::factory()->create(['owner_id' => $this->owner->id]);
        $date = now()->toDateString();

        DailyUsageStat::factory()->create([
            'tenant_id' => $this->owner->id,
            'date' => $date,
            'messages_received' => 20,
            'comments_received' => 0,
            'message_replies_sent' => 0,
            'comment_replies_sent' => 0,
            'ai_replies_sent' => 0,
            'manual_replies_sent' => 0,
            'human_handovers' => 0,
            'failed_replies' => 0,
        ]);

        $service = app(AnalyticsService::class);
        $result = $service->clientStats($member, $date, $date);

        $this->assertSame(20, $result['totals']['messages_received']);
    }

    public function test_admin_stats_returns_expected_keys(): void
    {
        $service = app(AnalyticsService::class);
        $stats = $service->adminStats();

        $this->assertArrayHasKey('total_users', $stats);
        $this->assertArrayHasKey('active_subscriptions', $stats);
        $this->assertArrayHasKey('total_revenue', $stats);
        $this->assertArrayHasKey('mrr', $stats);
        $this->assertArrayHasKey('total_messages', $stats);
        $this->assertArrayHasKey('daily_signups', $stats);
        $this->assertArrayHasKey('daily_revenue', $stats);
    }

    public function test_admin_stats_counts_owner_users_only(): void
    {
        User::factory()->count(2)->create();
        User::factory()->count(3)->create(['owner_id' => $this->owner->id]);

        $service = app(AnalyticsService::class);
        $stats = $service->adminStats();

        // owner + 2 new owners = 3 (setUp creates one owner)
        $this->assertSame(3, $stats['total_users']);
    }

    // --- Job tests ---

    public function test_generate_daily_analytics_job_creates_stat_for_each_owner(): void
    {
        $owner2 = User::factory()->create();

        $job = new GenerateDailyAnalyticsJob(Carbon::today());
        $job->handle(app(AnalyticsService::class));

        $this->assertDatabaseHas('daily_usage_stats', ['tenant_id' => $this->owner->id]);
        $this->assertDatabaseHas('daily_usage_stats', ['tenant_id' => $owner2->id]);
    }

    public function test_generate_daily_analytics_job_skips_team_members(): void
    {
        User::factory()->create(['owner_id' => $this->owner->id]);

        $job = new GenerateDailyAnalyticsJob(Carbon::today());
        $job->handle(app(AnalyticsService::class));

        // Only 1 row — for the owner, not the team member
        $this->assertSame(1, DailyUsageStat::count());
    }

    public function test_generate_monthly_analytics_job_creates_stat_for_each_owner(): void
    {
        $job = new GenerateMonthlyAnalyticsJob(2026, 5);
        $job->handle(app(AnalyticsService::class));

        $this->assertDatabaseHas('monthly_usage_stats', [
            'tenant_id' => $this->owner->id,
            'year' => 2026,
            'month' => 5,
        ]);
    }

    // --- API / route tests ---

    public function test_analytics_page_renders(): void
    {
        $response = $this->actingAs($this->owner)->get('/analytics');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('client/analytics'));
    }

    public function test_analytics_stats_endpoint_returns_json(): void
    {
        $date = now()->toDateString();
        DailyUsageStat::factory()->create([
            'tenant_id' => $this->owner->id,
            'date' => $date,
            'messages_received' => 7,
            'comments_received' => 3,
            'message_replies_sent' => 5,
            'comment_replies_sent' => 2,
            'ai_replies_sent' => 4,
            'manual_replies_sent' => 3,
            'human_handovers' => 1,
            'failed_replies' => 0,
        ]);

        $response = $this->actingAs($this->owner)->getJson("/analytics/stats?from={$date}&to={$date}");

        $response->assertOk();
        $response->assertJsonStructure(['totals', 'daily']);
        $response->assertJsonPath('totals.messages_received', 7);
    }

    public function test_analytics_stats_validates_date_range(): void
    {
        $response = $this->actingAs($this->owner)->getJson('/analytics/stats?from=bad-date&to=2026-01-01');
        $response->assertUnprocessable();
    }

    public function test_admin_analytics_page_requires_super_admin(): void
    {
        $response = $this->actingAs($this->owner)->get('/admin/analytics');
        $response->assertForbidden();
    }

    public function test_admin_analytics_page_renders_for_super_admin(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(\App\Models\Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']));

        $response = $this->actingAs($admin)->get('/admin/analytics');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('admin/analytics'));
    }
}
