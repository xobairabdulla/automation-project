<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\AiPrompt;
use App\Models\FacebookPage;
use App\Services\AIReplyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AiSettingsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $pageId = $request->query('page_id');

        $pages = FacebookPage::where('user_id', $user->id)
            ->where('is_connected', true)
            ->get(['id', 'page_name']);

        $aiPrompts = AiPrompt::where('tenant_id', $user->id)
            ->when($pageId, fn ($q) => $q->where('facebook_page_id', $pageId))
            ->get();

        return Inertia::render('client/ai-settings', [
            'aiPrompts' => $aiPrompts,
            'pages' => $pages,
            'selectedPageId' => $pageId ? (int) $pageId : null,
        ]);
    }

    public function upsert(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'facebook_page_id' => ['required', 'exists:facebook_pages,id'],
            'preferred_language' => ['nullable', 'string', 'max:10'],
            'tone' => ['required', 'in:friendly,professional,casual'],
            'reply_length' => ['required', 'in:short,medium,long'],
            'use_emoji' => ['boolean'],
            'unknown_answer_action' => ['required', 'in:human_handover,send_default'],
            'restricted_instructions' => ['nullable', 'string'],
        ]);

        $page = FacebookPage::where('id', $validated['facebook_page_id'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        AiPrompt::updateOrCreate(
            ['facebook_page_id' => $page->id],
            array_merge($validated, ['tenant_id' => $request->user()->id])
        );

        return back()->with('success', 'AI settings saved.');
    }

    public function testReply(Request $request, AIReplyService $aiReplyService): JsonResponse
    {
        $validated = $request->validate([
            'facebook_page_id' => ['required', 'exists:facebook_pages,id'],
            'question' => ['required', 'string', 'max:500'],
        ]);

        $page = FacebookPage::where('id', $validated['facebook_page_id'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $reply = $aiReplyService->generateReply($page, $validated['question']);

        return response()->json([
            'reply' => $reply ?? 'AI could not answer — would trigger human handover.',
            'unknown' => $reply === null,
        ]);
    }
}
