<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\UsageLimitService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClientDashboardController extends Controller
{
    public function index(Request $request, UsageLimitService $usageLimitService): Response
    {
        $user = $request->user();
        $subscription = $usageLimitService->currentSubscription($user)->load('plan');
        $usage = $usageLimitService->currentUsageLimit($user);

        $today = now()->startOfDay();

        $stats = [
            'messages_today' => $user->usageLogs()
                ->whereDate('created_at', $today)
                ->where('type', 'message_reply')
                ->sum('amount'),
            'comments_today' => $user->usageLogs()
                ->whereDate('created_at', $today)
                ->where('type', 'comment_reply')
                ->sum('amount'),
            'ai_replies_today' => $user->usageLogs()
                ->whereDate('created_at', $today)
                ->where('type', 'ai_reply')
                ->sum('amount'),
            'failed_replies_today' => $user->usageLogs()
                ->whereDate('created_at', $today)
                ->where('type', 'failed_reply')
                ->sum('amount'),
            'human_handovers_today' => 0,
            'connected_pages' => $user->facebookPages()->where('is_connected', true)->count(),
        ];

        $alerts = $this->buildAlerts($user, $usage, $subscription);

        $recentActivity = $user->usageLogs()
            ->latest()
            ->limit(10)
            ->get(['id', 'type', 'amount', 'created_at']);

        return Inertia::render('client/dashboard', [
            'subscription' => $subscription,
            'usage' => $usage,
            'stats' => $stats,
            'alerts' => $alerts,
            'recentActivity' => $recentActivity,
        ]);
    }

    /**
     * @param  \App\Models\UsageLimit  $usage
     * @param  \App\Models\Subscription  $subscription
     * @return array<int, array{type: string, message: string}>
     */
    private function buildAlerts(\App\Models\User $user, \App\Models\UsageLimit $usage, \App\Models\Subscription $subscription): array
    {
        $alerts = [];

        $msgPct = $usage->message_reply_limit > 0
            ? ($usage->message_reply_used / $usage->message_reply_limit) * 100
            : 0;

        $commentPct = $usage->comment_reply_limit > 0
            ? ($usage->comment_reply_used / $usage->comment_reply_limit) * 100
            : 0;

        $aiPct = $usage->ai_reply_limit > 0
            ? ($usage->ai_reply_used / $usage->ai_reply_limit) * 100
            : 0;

        if ($msgPct >= 100 || $commentPct >= 100 || $aiPct >= 100) {
            $alerts[] = ['type' => 'error', 'message' => 'One or more usage limits are exceeded. Automation is blocked.'];
        } elseif ($msgPct >= 80 || $commentPct >= 80 || $aiPct >= 80) {
            $alerts[] = ['type' => 'warning', 'message' => 'Usage is above 80%. Upgrade or extend limits soon.'];
        }

        if (in_array($subscription->status, ['expired', 'cancelled', 'pending'])) {
            $alerts[] = ['type' => 'warning', 'message' => 'Subscription is not active. Automation may be disabled.'];
        }

        $connectedPages = $user->facebookPages()->where('is_connected', true)->count();
        if ($connectedPages === 0) {
            $alerts[] = ['type' => 'info', 'message' => 'No Facebook Page connected. Connect a page to start automation.'];
        }

        return $alerts;
    }
}
