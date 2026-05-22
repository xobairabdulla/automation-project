<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\FacebookPage;
use App\Models\ReplyTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReplyTemplateController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $pageId = $request->query('page_id');

        $pages = FacebookPage::where('user_id', $user->id)
            ->where('is_connected', true)
            ->get(['id', 'page_name']);

        $templates = ReplyTemplate::where('tenant_id', $user->id)
            ->when($pageId, fn ($q) => $q->where('facebook_page_id', $pageId))
            ->latest()
            ->get();

        return Inertia::render('client/reply-templates', [
            'templates' => $templates,
            'pages' => $pages,
            'selectedPageId' => $pageId ? (int) $pageId : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'facebook_page_id' => ['required', 'exists:facebook_pages,id'],
            'name' => ['required', 'string', 'max:255'],
            'channel' => ['required', 'in:message,comment,both'],
            'language' => ['nullable', 'string', 'max:10'],
            'body' => ['required', 'string'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $page = FacebookPage::where('id', $validated['facebook_page_id'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        ReplyTemplate::create([
            'tenant_id' => $request->user()->id,
            'facebook_page_id' => $page->id,
            'name' => $validated['name'],
            'channel' => $validated['channel'],
            'language' => $validated['language'] ?? 'en',
            'body' => $validated['body'],
            'status' => $validated['status'],
        ]);

        return back()->with('success', 'Template created.');
    }

    public function update(Request $request, ReplyTemplate $replyTemplate): RedirectResponse
    {
        abort_if($replyTemplate->tenant_id !== $request->user()->id, 403);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'channel' => ['sometimes', 'in:message,comment,both'],
            'language' => ['nullable', 'string', 'max:10'],
            'body' => ['sometimes', 'string'],
            'status' => ['sometimes', 'in:active,inactive'],
        ]);

        $replyTemplate->update($validated);

        return back()->with('success', 'Template updated.');
    }

    public function destroy(Request $request, ReplyTemplate $replyTemplate): RedirectResponse
    {
        abort_if($replyTemplate->tenant_id !== $request->user()->id, 403);

        $replyTemplate->delete();

        return back()->with('success', 'Template deleted.');
    }
}
