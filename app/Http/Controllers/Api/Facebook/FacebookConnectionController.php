<?php

namespace App\Http\Controllers\Api\Facebook;

use App\Http\Controllers\Controller;
use App\Models\FacebookPage;
use App\Services\FacebookService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class FacebookConnectionController extends Controller
{
    public function connectUrl(Request $request): JsonResponse
    {
        $state = Str::random(40);
        // Store state in cache keyed by user — API requests have no session
        Cache::put("fb_oauth_state_{$request->user()->id}", $state, now()->addMinutes(10));

        return ApiResponse::success([
            'url' => app(FacebookService::class)->getOAuthUrl($state),
            'state' => $state,
        ], 'OAuth URL generated');
    }

    public function pages(Request $request): JsonResponse
    {
        $pages = $request->user()
            ->facebookPages()
            ->latest()
            ->get()
            ->map(fn (FacebookPage $p) => [
                'id' => $p->id,
                'page_id' => $p->page_id,
                'page_name' => $p->page_name,
                'is_connected' => $p->is_connected,
                'automation_enabled' => $p->automation_enabled,
                'message_automation_enabled' => $p->message_automation_enabled,
                'comment_automation_enabled' => $p->comment_automation_enabled,
                'token_expired' => $p->isTokenExpired(),
                'last_webhook_received_at' => $p->last_webhook_received_at,
            ]);

        return ApiResponse::success($pages, 'Pages loaded');
    }

    public function connectPage(Request $request, FacebookPage $page): JsonResponse
    {
        abort_if($page->user_id !== $request->user()->id, 403);
        app(FacebookService::class)->subscribePageToWebhooks($page);
        $page->update(['is_connected' => true]);

        return ApiResponse::success(null, 'Page connected');
    }

    public function disconnectPage(Request $request, FacebookPage $page): JsonResponse
    {
        abort_if($page->user_id !== $request->user()->id, 403);
        $page->update([
            'is_connected' => false,
            'automation_enabled' => false,
            'message_automation_enabled' => false,
            'comment_automation_enabled' => false,
        ]);

        return ApiResponse::success(null, 'Page disconnected');
    }

    public function enableAutomation(Request $request, FacebookPage $page): JsonResponse
    {
        abort_if($page->user_id !== $request->user()->id, 403);
        $page->update(['automation_enabled' => true]);

        return ApiResponse::success(null, 'Automation enabled');
    }

    public function disableAutomation(Request $request, FacebookPage $page): JsonResponse
    {
        abort_if($page->user_id !== $request->user()->id, 403);
        $page->update(['automation_enabled' => false]);

        return ApiResponse::success(null, 'Automation disabled');
    }
}
