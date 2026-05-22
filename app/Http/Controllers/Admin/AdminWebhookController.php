<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessFacebookWebhookJob;
use App\Models\WebhookEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminWebhookController extends Controller
{
    public function index(Request $request): Response
    {
        $events = WebhookEvent::query()
            ->with('tenant:id,name,email', 'facebookPage:id,page_name,page_id')
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->event_type, fn ($q, $v) => $q->where('event_type', $v))
            ->when($request->tenant_id, fn ($q, $v) => $q->where('tenant_id', $v))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('admin/webhook-logs/index', [
            'events' => $events,
            'filters' => $request->only('status', 'event_type', 'tenant_id'),
        ]);
    }

    public function show(WebhookEvent $webhookEvent): Response
    {
        $webhookEvent->load('tenant:id,name,email', 'facebookPage:id,page_name,page_id', 'logs');

        return Inertia::render('admin/webhook-logs/show', [
            'event' => $webhookEvent,
        ]);
    }

    public function retry(WebhookEvent $webhookEvent): RedirectResponse
    {
        abort_if($webhookEvent->status !== 'failed', 422, 'Only failed events can be retried.');

        $webhookEvent->update(['status' => 'queued', 'error_message' => null]);
        ProcessFacebookWebhookJob::dispatch($webhookEvent);

        return back()->with('success', 'Event queued for retry.');
    }
}
