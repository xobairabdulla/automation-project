<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Plan;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminPlanController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::success(Plan::latest()->get(), 'Plans listed');
    }

    public function store(Request $request): JsonResponse
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
            'features_json' => ['nullable', 'array'],
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

        return ApiResponse::success($plan, 'Plan created', 201);
    }

    public function update(Request $request, Plan $plan): JsonResponse
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
            'features_json' => ['nullable', 'array'],
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

        return ApiResponse::success($plan, 'Plan updated');
    }

    public function destroy(Request $request, Plan $plan): JsonResponse
    {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'plan.deleted',
            'entity_type' => 'Plan',
            'entity_id' => $plan->id,
            'old_values_json' => $plan->toArray(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $plan->update(['status' => 'inactive']);

        return ApiResponse::success(null, 'Plan deactivated');
    }
}
