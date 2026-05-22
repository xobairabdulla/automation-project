<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminPlanController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/plans/index', [
            'plans' => Plan::latest()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'monthly_price' => ['required', 'numeric', 'min:0'],
            'yearly_price' => ['required', 'numeric', 'min:0'],
            'message_reply_limit' => ['required', 'integer', 'min:0'],
            'comment_reply_limit' => ['required', 'integer', 'min:0'],
            'ai_reply_limit' => ['required', 'integer', 'min:0'],
            'connected_page_limit' => ['required', 'integer', 'min:0'],
            'team_member_limit' => ['required', 'integer', 'min:0'],
            'knowledge_base_limit' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $plan = Plan::create($validated);

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'plan.created',
            'entity_type' => 'Plan',
            'entity_id' => $plan->id,
            'new_values_json' => $validated,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', 'Plan created.');
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'monthly_price' => ['sometimes', 'numeric', 'min:0'],
            'yearly_price' => ['sometimes', 'numeric', 'min:0'],
            'message_reply_limit' => ['sometimes', 'integer', 'min:0'],
            'comment_reply_limit' => ['sometimes', 'integer', 'min:0'],
            'ai_reply_limit' => ['sometimes', 'integer', 'min:0'],
            'connected_page_limit' => ['sometimes', 'integer', 'min:0'],
            'team_member_limit' => ['sometimes', 'integer', 'min:0'],
            'knowledge_base_limit' => ['sometimes', 'integer', 'min:0'],
            'status' => ['sometimes', 'in:active,inactive'],
        ]);

        $old = $plan->toArray();
        $plan->update($validated);

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'plan.updated',
            'entity_type' => 'Plan',
            'entity_id' => $plan->id,
            'old_values_json' => $old,
            'new_values_json' => $validated,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', 'Plan updated.');
    }

    public function deactivate(Request $request, Plan $plan): RedirectResponse
    {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'plan.deactivated',
            'entity_type' => 'Plan',
            'entity_id' => $plan->id,
            'old_values_json' => ['status' => $plan->status],
            'new_values_json' => ['status' => 'inactive'],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $plan->update(['status' => 'inactive']);

        return back()->with('success', 'Plan deactivated.');
    }
}
