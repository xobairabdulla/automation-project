<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\UsageLimitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'string', 'exists:roles,slug'],
            'status' => ['required', 'in:active,suspended,pending'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'status' => $validated['status'],
        ]);

        $role = Role::where('slug', $validated['role'])->firstOrFail();
        $user->roles()->attach($role->id);

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'user.created',
            'entity_type' => 'User',
            'entity_id' => $user->id,
            'new_values_json' => ['name' => $user->name, 'email' => $user->email, 'role' => $validated['role']],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', 'User created.');
    }

    public function index(Request $request): Response
    {
        $users = User::query()
            ->with('roles', 'subscriptions.plan')
            ->when($request->search, fn ($q, $search) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/users/index', [
            'users' => $users,
            'filters' => $request->only('search', 'status'),
        ]);
    }

    public function show(User $user): Response
    {
        $user->load('roles', 'tenant', 'subscriptions.plan');
        $usageLimit = $user->usageLimits()->latest()->first();
        $history = $user->usageLogs()->latest()->limit(20)->get();

        return Inertia::render('admin/users/show', [
            'user' => $user,
            'usageLimit' => $usageLimit,
            'history' => $history,
            'plans' => Plan::where('status', 'active')->get(['id', 'name']),
        ]);
    }

    public function suspend(Request $request, User $user): RedirectResponse
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

        return back()->with('success', 'User suspended.');
    }

    public function activate(Request $request, User $user): RedirectResponse
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

        return back()->with('success', 'User activated.');
    }

    public function assignPlan(Request $request, User $user, UsageLimitService $usageLimitService, PaymentService $paymentService): RedirectResponse
    {
        $validated = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
            'billing_cycle' => ['required', 'in:monthly,yearly'],
        ]);

        $plan = Plan::findOrFail($validated['plan_id']);

        // Expire all existing active/trial subscriptions
        $user->subscriptions()->whereIn('status', ['active', 'trial'])->update(['status' => 'expired']);

        $subscription = $user->subscriptions()->create([
            'tenant_id' => $user->tenant_id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'billing_cycle' => $validated['billing_cycle'],
            'starts_at' => now(),
            'ends_at' => $validated['billing_cycle'] === 'yearly' ? now()->addYear() : now()->addMonth(),
            'next_billing_at' => $validated['billing_cycle'] === 'yearly' ? now()->addYear() : now()->addMonth(),
        ]);

        $usageLimitService->createUsageLimitForSubscription($subscription);
        $paymentService->recordAdminGrantedSubscription($request->user(), $user, $subscription);

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'user.plan_assigned',
            'entity_type' => 'User',
            'entity_id' => $user->id,
            'new_values_json' => $validated,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', 'Plan assigned.');
    }

    public function extendLimit(Request $request, User $user, UsageLimitService $usageLimitService): RedirectResponse
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

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'user.limit_extended',
            'entity_type' => 'UsageLimit',
            'entity_id' => $usageLimit->id,
            'new_values_json' => $validated,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', 'Limits extended.');
    }
}
