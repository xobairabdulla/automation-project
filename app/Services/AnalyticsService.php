<?php

namespace App\Services;

use App\Models\DailyUsageStat;
use App\Models\FacebookComment;
use App\Models\Message;
use App\Models\MonthlyUsageStat;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UsageLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    /**
     * @return array<string, int>
     */
    public function computeDailyStats(User $user, Carbon $date): array
    {
        $tenantId = $user->effectiveTenantId();
        $dateStr = $date->toDateString();

        $messagesReceived = Message::query()
            ->where('tenant_id', $tenantId)
            ->where('direction', 'inbound')
            ->whereDate('created_at', $dateStr)
            ->count();

        $commentsReceived = FacebookComment::query()
            ->where('tenant_id', $tenantId)
            ->whereDate('created_at', $dateStr)
            ->count();

        $messageRepliesSent = Message::query()
            ->where('tenant_id', $tenantId)
            ->where('direction', 'outbound')
            ->whereDate('created_at', $dateStr)
            ->count();

        $commentRepliesSent = (int) UsageLog::query()
            ->where('tenant_id', $tenantId)
            ->where('type', 'comment_reply')
            ->whereDate('created_at', $dateStr)
            ->sum('amount');

        $aiRepliesSent = (int) UsageLog::query()
            ->where('tenant_id', $tenantId)
            ->where('type', 'ai_reply')
            ->whereDate('created_at', $dateStr)
            ->sum('amount');

        $manualRepliesSent = max(0, ($messageRepliesSent + $commentRepliesSent) - $aiRepliesSent);

        $humanHandovers = (int) UsageLog::query()
            ->where('tenant_id', $tenantId)
            ->where('type', 'human_handover')
            ->whereDate('created_at', $dateStr)
            ->sum('amount');

        $failedReplies = (int) UsageLog::query()
            ->where('tenant_id', $tenantId)
            ->where('type', 'failed_reply')
            ->whereDate('created_at', $dateStr)
            ->sum('amount');

        return [
            'messages_received' => (int) $messagesReceived,
            'comments_received' => (int) $commentsReceived,
            'message_replies_sent' => (int) $messageRepliesSent,
            'comment_replies_sent' => $commentRepliesSent,
            'ai_replies_sent' => $aiRepliesSent,
            'manual_replies_sent' => $manualRepliesSent,
            'human_handovers' => $humanHandovers,
            'failed_replies' => $failedReplies,
        ];
    }

    /**
     * @return array<string, int>
     */
    public function computeMonthlyStats(User $user, int $year, int $month): array
    {
        $from = Carbon::create($year, $month, 1)->startOfMonth();
        $to = $from->copy()->endOfMonth();
        $tenantId = $user->effectiveTenantId();

        $rows = DailyUsageStat::query()
            ->where('tenant_id', $tenantId)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get();

        return [
            'messages_received' => (int) $rows->sum('messages_received'),
            'comments_received' => (int) $rows->sum('comments_received'),
            'message_replies_sent' => (int) $rows->sum('message_replies_sent'),
            'comment_replies_sent' => (int) $rows->sum('comment_replies_sent'),
            'ai_replies_sent' => (int) $rows->sum('ai_replies_sent'),
            'manual_replies_sent' => (int) $rows->sum('manual_replies_sent'),
            'human_handovers' => (int) $rows->sum('human_handovers'),
            'failed_replies' => (int) $rows->sum('failed_replies'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function clientStats(User $user, string $from, string $to): array
    {
        $tenantId = $user->effectiveTenantId();

        $rows = DailyUsageStat::query()
            ->where('tenant_id', $tenantId)
            ->whereBetween('date', [$from, $to])
            ->orderBy('date')
            ->get();

        $totals = [
            'messages_received' => (int) $rows->sum('messages_received'),
            'comments_received' => (int) $rows->sum('comments_received'),
            'message_replies_sent' => (int) $rows->sum('message_replies_sent'),
            'comment_replies_sent' => (int) $rows->sum('comment_replies_sent'),
            'ai_replies_sent' => (int) $rows->sum('ai_replies_sent'),
            'manual_replies_sent' => (int) $rows->sum('manual_replies_sent'),
            'human_handovers' => (int) $rows->sum('human_handovers'),
            'failed_replies' => (int) $rows->sum('failed_replies'),
        ];

        $daily = $rows->map(fn ($r) => [
            'date' => $r->date,
            'messages_received' => $r->messages_received,
            'comments_received' => $r->comments_received,
            'replies_sent' => $r->message_replies_sent + $r->comment_replies_sent,
            'ai_replies' => $r->ai_replies_sent,
            'human_handovers' => $r->human_handovers,
        ])->values()->all();

        return compact('totals', 'daily');
    }

    /**
     * @return array<string, mixed>
     */
    public function adminStats(): array
    {
        $totalUsers = User::query()->whereNull('owner_id')->count();
        $activeSubscriptions = Subscription::query()->whereIn('status', ['active', 'trial'])->count();

        $totalRevenue = (float) Payment::query()
            ->where('status', 'completed')
            ->where('type', '!=', 'admin_granted')
            ->sum('amount');

        $mrr = (float) Payment::query()
            ->where('status', 'completed')
            ->where('type', 'subscription')
            ->where('paid_at', '>=', now()->startOfMonth())
            ->sum('amount');

        $totalMessages = (int) DB::table('daily_usage_stats')->sum('messages_received');
        $totalComments = (int) DB::table('daily_usage_stats')->sum('comments_received');
        $totalReplies = (int) DB::table('daily_usage_stats')->sum('message_replies_sent')
            + (int) DB::table('daily_usage_stats')->sum('comment_replies_sent');

        $dailySignups = User::query()
            ->whereNull('owner_id')
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($r) => ['date' => $r->date, 'count' => (int) $r->count])
            ->values()
            ->all();

        $dailyRevenue = Payment::query()
            ->where('status', 'completed')
            ->where('type', '!=', 'admin_granted')
            ->where('paid_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(paid_at) as date, SUM(amount) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($r) => ['date' => $r->date, 'total' => (float) $r->total])
            ->values()
            ->all();

        return [
            'total_users' => (int) $totalUsers,
            'active_subscriptions' => (int) $activeSubscriptions,
            'total_revenue' => $totalRevenue,
            'mrr' => $mrr,
            'total_messages' => $totalMessages,
            'total_comments' => $totalComments,
            'total_replies' => $totalReplies,
            'daily_signups' => $dailySignups,
            'daily_revenue' => $dailyRevenue,
        ];
    }

    public function upsertDailyStat(int $tenantId, Carbon $date, array $stats): DailyUsageStat
    {
        return DailyUsageStat::updateOrCreate(
            ['tenant_id' => $tenantId, 'date' => $date->toDateString()],
            $stats
        );
    }

    public function upsertMonthlyStat(int $tenantId, int $year, int $month, array $stats): MonthlyUsageStat
    {
        return MonthlyUsageStat::updateOrCreate(
            ['tenant_id' => $tenantId, 'year' => $year, 'month' => $month],
            $stats
        );
    }
}
