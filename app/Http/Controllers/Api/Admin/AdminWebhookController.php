<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessFacebookWebhookJob;
use App\Models\WebhookEvent;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminWebhookController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $events = WebhookEvent::query()
            ->with('tenant:id,name,email', 'facebookPage:id,page_name,page_id')
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->event_type, fn ($q, $v) => $q->where('event_type', $v))
            ->when($request->tenant_id, fn ($q, $v) => $q->where('tenant_id', $v))
            ->latest()
            ->paginate(30);

        return ApiResponse::success($events, 'Webhook events loaded');
    }

    public function show(WebhookEvent $webhookEvent): JsonResponse
    {
        $webhookEvent->load('tenant:id,name,email', 'facebookPage:id,page_name,page_id', 'logs');

        return ApiResponse::success($webhookEvent, 'Webhook event loaded');
    }

    public function retry(WebhookEvent $webhookEvent): JsonResponse
    {
        abort_if($webhookEvent->status !== 'failed', 422, 'Only failed events can be retried.');

        $webhookEvent->update(['status' => 'queued', 'error_message' => null]);
        ProcessFacebookWebhookJob::dispatch($webhookEvent);

        return ApiResponse::success(null, 'Event queued for retry.');
    }
}
