<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Plan;
use App\Models\User;
use App\Services\UsageLimitService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $users = User::query()
            ->with('roles', 'subscriptions.plan')
            ->when($request->search, fn ($q, $search) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(20);

        return ApiResponse::success($users, 'Users listed');
    }

    public function show(User $user): JsonResponse
    {
        $user->load('roles', 'tenant', 'subscriptions.plan');

        $usageLimit = $user->usageLimits()->latest()->first();

        return ApiResponse::success([
            'user' => $user,
            'usage_limit' => $usageLimit,
        ], 'User details loaded');
    }

    public function suspend(Request $request, User $user): JsonResponse
    {
        $old = $user->status;
        $user->update(['status' => 'suspended']);

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'user.suspended',
            'entity_type' => 'User',
            'entity_id' => $user->id,
            'old_values_json' => ['status' => $old],
            'new_values_json' => ['status' => 'suspended'],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return ApiResponse::success(null, 'User suspended');
    }

    public function activate(Request $request, User $user): JsonResponse
    {
        $old = $user->status;
        $user->update(['status' => 'active']);

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'user.activated',
            'entity_type' => 'User',
            'entity_id' => $user->id,
            'old_values_json' => ['status' => $old],
            'new_values_json' => ['status' => 'active'],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return ApiResponse::success(null, 'User activated');
    }

    public function assignPlan(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
            'billing_cycle' => ['required', 'in:monthly,yearly'],
        ]);

        $plan = Plan::findOrFail($validated['plan_id']);

        $subscription = $user->subscriptions()->create([
            'tenant_id' => $user->tenant_id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'billing_cycle' => $validated['billing_cycle'],
            'starts_at' => now(),
            'ends_at' => $validated['billing_cycle'] === 'yearly' ? now()->addYear() : now()->addMonth(),
            'next_billing_at' => $validated['billing_cycle'] === 'yearly' ? now()->addYear() : now()->addMonth(),
        ]);

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'user.plan_assigned',
            'entity_type' => 'User',
            'entity_id' => $user->id,
            'new_values_json' => ['plan_id' => $plan->id, 'billing_cycle' => $validated['billing_cycle']],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return ApiResponse::success($subscription->load('plan'), 'Plan assigned');
    }

    public function extendLimit(Request $request, User $user, UsageLimitService $usageLimitService): JsonResponse
    {
        $validated = $request->validate([
            'message_reply_extra' => ['sometimes', 'integer', 'min:0'],
            'comment_reply_extra' => ['sometimes', 'integer', 'min:0'],
            'ai_reply_extra' => ['sometimes', 'integer', 'min:0'],
        ]);

        $usageLimit = $usageLimitService->currentUsageLimit($user);

        $usageLimit->increment('message_reply_limit', $validated['message_reply_extra'] ?? 0);
        $usageLimit->increment('comment_reply_limit', $validated['comment_reply_extra'] ?? 0);
        $usageLimit->increment('ai_reply_limit', $validated['ai_reply_extra'] ?? 0);
        $usageLimit->refresh();

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'user.limit_extended',
            'entity_type' => 'UsageLimit',
            'entity_id' => $usageLimit->id,
            'new_values_json' => $validated,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return ApiResponse::success($usageLimit, 'Usage limits extended');
    }

    public function usageHistory(User $user): JsonResponse
    {
        $history = $user->usageLogs()->latest()->paginate(30);

        return ApiResponse::success($history, 'Usage history loaded');
    }
}
