<?php

namespace App\Http\Controllers\Facebook;

use App\Http\Controllers\Controller;
use App\Models\FacebookPage;
use App\Services\FacebookService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class FacebookPageController extends Controller
{
    public function index(Request $request): Response
    {
        $pages = $request->user()
            ->facebookPages()
            ->with('facebookAccount')
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
                'last_webhook_received_at' => $p->last_webhook_received_at?->toDateTimeString(),
                'facebook_account_name' => $p->facebookAccount->name,
            ]);

        return Inertia::render('client/facebook-pages', [
            'pages' => $pages,
            'connectUrl' => route('facebook.connect'),
        ]);
    }

    public function connect(Request $request): RedirectResponse
    {
        $state = Str::random(40);
        $request->session()->put('facebook_oauth_state', $state);

        $url = app(FacebookService::class)->getOAuthUrl($state);

        return redirect()->away($url);
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->has('error')) {
            return redirect()->route('facebook.pages')
                ->with('error', 'Facebook connection was cancelled.');
        }

        $state = $request->session()->pull('facebook_oauth_state');

        if (! $state || $state !== $request->state) {
            return redirect()->route('facebook.pages')
                ->with('error', 'Invalid OAuth state. Please try again.');
        }

        try {
            $service = app(FacebookService::class);
            $tokenData = $service->exchangeCodeForToken($request->code);
            $service->saveAccountAndPages(
                $request->user(),
                $tokenData['access_token'],
                $tokenData['expires_in'] ?? null,
            );
        } catch (\Throwable $e) {
            return redirect()->route('facebook.pages')
                ->with('error', 'Facebook connection failed: '.$e->getMessage());
        }

        return redirect()->route('facebook.pages')
            ->with('success', 'Facebook account connected successfully.');
    }

    public function connectPage(Request $request, FacebookPage $page): RedirectResponse
    {
        $this->authorizePageAccess($request->user()->id, $page);
        $page->update(['is_connected' => true]);

        return back()->with('success', "Page \"{$page->page_name}\" connected.");
    }

    public function disconnectPage(Request $request, FacebookPage $page): RedirectResponse
    {
        $this->authorizePageAccess($request->user()->id, $page);
        $page->update([
            'is_connected' => false,
            'automation_enabled' => false,
            'message_automation_enabled' => false,
            'comment_automation_enabled' => false,
        ]);

        return back()->with('success', "Page \"{$page->page_name}\" disconnected.");
    }

    public function enableAutomation(Request $request, FacebookPage $page): RedirectResponse
    {
        $this->authorizePageAccess($request->user()->id, $page);

        $validated = $request->validate([
            'message_automation' => ['boolean'],
            'comment_automation' => ['boolean'],
        ]);

        $page->update([
            'automation_enabled' => true,
            'message_automation_enabled' => $validated['message_automation'] ?? $page->message_automation_enabled,
            'comment_automation_enabled' => $validated['comment_automation'] ?? $page->comment_automation_enabled,
        ]);

        return back()->with('success', 'Automation enabled.');
    }

    public function disableAutomation(Request $request, FacebookPage $page): RedirectResponse
    {
        $this->authorizePageAccess($request->user()->id, $page);
        $page->update([
            'automation_enabled' => false,
            'message_automation_enabled' => false,
            'comment_automation_enabled' => false,
        ]);

        return back()->with('success', 'Automation disabled.');
    }

    private function authorizePageAccess(int $userId, FacebookPage $page): void
    {
        abort_if($page->user_id !== $userId, 403, 'Access denied.');
    }
}
