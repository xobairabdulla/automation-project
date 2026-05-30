<?php

namespace App\Http\Controllers\Api\Facebook;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessFacebookWebhookJob;
use App\Models\FacebookPage;
use App\Models\WebhookEvent;
use App\Models\WebhookEventLog;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class FacebookWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        $expectedToken = config('services.meta.webhook_verify_token');

        if ($mode === 'subscribe' && $token === $expectedToken) {
            return response($challenge, 200);
        }

        return response('Verification failed', 403);
    }

    public function receive(Request $request): Response
    {
        if (! $this->isValidSignature($request)) {
            Log::warning('Facebook webhook: invalid signature', ['ip' => $request->ip()]);

            return response('Invalid signature', 403);
        }

        $payload = $request->json()->all();

        if (($payload['object'] ?? '') !== 'page') {
            return response('OK', 200);
        }

        foreach ($payload['entry'] ?? [] as $entry) {
            $this->processEntry($entry, $payload);
        }

        return response('OK', 200);
    }

    private function processEntry(array $entry, array $fullPayload): void
    {
        $pageId = $entry['id'] ?? null;

        if (! $pageId) {
            return;
        }

        $page = FacebookPage::where('page_id', $pageId)->where('is_connected', true)->first();

        if (! $page) {
            Log::info("Facebook webhook: no connected page found for page_id={$pageId}");

            return;
        }

        $page->update(['last_webhook_received_at' => now()]);

        $eventType = $this->detectEventType($entry);
        $externalEventId = $this->extractExternalEventId($entry, $eventType);

        // Deduplicate by external_event_id per tenant
        if ($externalEventId && WebhookEvent::where('tenant_id', $page->user_id)->where('external_event_id', $externalEventId)->exists()) {
            Log::info("Facebook webhook: duplicate event ignored, external_event_id={$externalEventId}");

            return;
        }

        $event = WebhookEvent::create([
            'tenant_id' => $page->user_id,
            'facebook_page_id' => $page->id,
            'event_type' => $eventType,
            'external_event_id' => $externalEventId,
            'payload_json' => $entry,
            'status' => 'queued',
        ]);

        WebhookEventLog::create([
            'webhook_event_id' => $event->id,
            'status' => 'queued',
            'message' => 'Event received and queued for processing.',
        ]);

        ProcessFacebookWebhookJob::dispatch($event);
    }

    private function detectEventType(array $entry): string
    {
        if (! empty($entry['messaging'])) {
            $msg = $entry['messaging'][0] ?? [];

            if ($msg['message']['is_echo'] ?? false) {
                return 'messaging_echo';
            }

            return isset($msg['message']) ? 'message' : 'messaging_other';
        }

        if (! empty($entry['changes'])) {
            $change = $entry['changes'][0] ?? [];

            return 'comment_'.(($change['field'] ?? '') ?: 'other');
        }

        return 'unknown';
    }

    private function extractExternalEventId(array $entry, string $eventType): ?string
    {
        if (str_starts_with($eventType, 'message')) {
            $msg = ($entry['messaging'][0] ?? [])['message'] ?? [];

            return $msg['mid'] ?? null;
        }

        if (str_starts_with($eventType, 'comment')) {
            $value = ($entry['changes'][0] ?? [])['value'] ?? [];

            return $value['comment_id'] ?? null;
        }

        return null;
    }

    private function isValidSignature(Request $request): bool
    {
        $secret = config('services.meta.app_secret');

        // Skip verification when secret is not configured (local dev)
        if (! $secret) {
            return true;
        }

        $signature = $request->header('X-Hub-Signature-256');

        if (! $signature) {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }
}
