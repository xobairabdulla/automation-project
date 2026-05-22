<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Services\UsageLimitService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientDashboardController extends Controller
{
    public function index(Request $request, UsageLimitService $usageLimitService): JsonResponse
    {
        $user = $request->user();
        $subscription = $usageLimitService->currentSubscription($user)->load('plan');
        $usage = $usageLimitService->currentUsageLimit($user);
        $today = now()->startOfDay();

        return ApiResponse::success([
            'subscription' => $subscription,
            'usage' => $usage,
            'stats' => [
                'messages_today' => $user->usageLogs()->whereDate('created_at', $today)->where('type', 'message_reply')->sum('amount'),
                'comments_today' => $user->usageLogs()->whereDate('created_at', $today)->where('type', 'comment_reply')->sum('amount'),
                'ai_replies_today' => $user->usageLogs()->whereDate('created_at', $today)->where('type', 'ai_reply')->sum('amount'),
                'failed_replies_today' => $user->usageLogs()->whereDate('created_at', $today)->where('type', 'failed_reply')->sum('amount'),
                'human_handovers_today' => 0,
                'connected_pages' => $user->facebookPages()->where('is_connected', true)->count(),
            ],
        ], 'Dashboard loaded');
    }

    public function alerts(Request $request, UsageLimitService $usageLimitService): JsonResponse
    {
        $user = $request->user();
        $subscription = $usageLimitService->currentSubscription($user);
        $usage = $usageLimitService->currentUsageLimit($user);
        $alerts = [];

        $limits = [
            'message_reply' => [$usage->message_reply_used, $usage->message_reply_limit],
            'comment_reply' => [$usage->comment_reply_used, $usage->comment_reply_limit],
            'ai_reply' => [$usage->ai_reply_used, $usage->ai_reply_limit],
        ];

        foreach ($limits as $label => [$used, $limit]) {
            if ($limit === 0) {
                continue;
            }

            $pct = ($used / $limit) * 100;

            if ($pct >= 100) {
                $alerts[] = ['type' => 'error', 'message' => ucwords(str_replace('_', ' ', $label)).' limit exceeded.'];
            } elseif ($pct >= 80) {
                $alerts[] = ['type' => 'warning', 'message' => ucwords(str_replace('_', ' ', $label)).' usage above 80%.'];
            }
        }

        if (in_array($subscription->status, ['expired', 'cancelled', 'pending'])) {
            $alerts[] = ['type' => 'warning', 'message' => 'Subscription is not active.'];
        }

        if ($user->facebookPages()->where('is_connected', true)->count() === 0) {
            $alerts[] = ['type' => 'info', 'message' => 'No Facebook Page connected.'];
        }

        return ApiResponse::success($alerts, 'Alerts loaded');
    }

    public function recentActivity(Request $request): JsonResponse
    {
        $activity = $request->user()
            ->usageLogs()
            ->latest()
            ->limit(20)
            ->get(['id', 'type', 'amount', 'created_at']);

        return ApiResponse::success($activity, 'Recent activity loaded');
    }
}
