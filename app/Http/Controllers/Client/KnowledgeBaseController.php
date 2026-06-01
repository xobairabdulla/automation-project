<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\FacebookPage;
use App\Models\KnowledgeBase;
use App\Models\KnowledgeBaseItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KnowledgeBaseController extends Controller
{
    private const VALID_CATEGORIES = [
        'faq', 'product', 'delivery', 'payment', 'order',
        'complaint', 'support', 'instruction',
        'pricing', 'refund', 'contact', 'business_hours',
        'restricted_topic', 'other',
    ];

    public function index(Request $request): Response
    {
        $user = $request->user();
        $pageId = $request->query('page_id');

        $pages = FacebookPage::where('user_id', $user->id)
            ->where('is_connected', true)
            ->get(['id', 'page_name']);

        $knowledgeBases = KnowledgeBase::with(['items' => fn ($q) => $q->orderByDesc('priority')->orderBy('category')])
            ->where('tenant_id', $user->id)
            ->when($pageId, fn ($q) => $q->where('facebook_page_id', $pageId))
            ->get();

        return Inertia::render('client/knowledge-base', [
            'knowledgeBases' => $knowledgeBases,
            'pages' => $pages,
            'selectedPageId' => $pageId ? (int) $pageId : null,
        ]);
    }

    public function storeBase(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'facebook_page_id' => ['required', 'exists:facebook_pages,id'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $page = FacebookPage::where('id', $validated['facebook_page_id'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        KnowledgeBase::firstOrCreate(
            ['facebook_page_id' => $page->id],
            ['tenant_id' => $request->user()->id, 'name' => $validated['name'], 'status' => 'active']
        );

        return back()->with('success', 'Knowledge base created.');
    }

    public function storeItem(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'knowledge_base_id' => ['required', 'exists:knowledge_bases,id'],
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'in:'.implode(',', self::VALID_CATEGORIES)],
            'content' => ['required', 'string'],
            'keywords' => ['nullable', 'string', 'max:1000'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:10'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $kb = KnowledgeBase::where('id', $validated['knowledge_base_id'])
            ->where('tenant_id', $request->user()->id)
            ->firstOrFail();

        KnowledgeBaseItem::create([
            'tenant_id' => $request->user()->id,
            'knowledge_base_id' => $kb->id,
            'title' => $validated['title'],
            'category' => $validated['category'],
            'content' => $validated['content'],
            'keywords' => $this->parseKeywords($validated['keywords'] ?? null),
            'priority' => $validated['priority'] ?? 0,
            'status' => $validated['status'],
        ]);

        return back()->with('success', 'Item added.');
    }

    public function updateItem(Request $request, KnowledgeBaseItem $knowledgeBaseItem): RedirectResponse
    {
        abort_if($knowledgeBaseItem->tenant_id !== $request->user()->id, 403);

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'category' => ['sometimes', 'in:'.implode(',', self::VALID_CATEGORIES)],
            'content' => ['sometimes', 'string'],
            'keywords' => ['nullable', 'string', 'max:1000'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:10'],
            'status' => ['sometimes', 'in:active,inactive'],
        ]);

        if (array_key_exists('keywords', $validated)) {
            $validated['keywords'] = $this->parseKeywords($validated['keywords']);
        }

        if (array_key_exists('priority', $validated)) {
            $validated['priority'] = $validated['priority'] ?? 0;
        }

        $knowledgeBaseItem->update($validated);

        return back()->with('success', 'Item updated.');
    }

    public function destroyItem(Request $request, KnowledgeBaseItem $knowledgeBaseItem): RedirectResponse
    {
        abort_if($knowledgeBaseItem->tenant_id !== $request->user()->id, 403);

        $knowledgeBaseItem->delete();

        return back()->with('success', 'Item deleted.');
    }

    /**
     * Convert comma-separated keyword string to a clean array, or null if empty.
     *
     * @return list<string>|null
     */
    private function parseKeywords(?string $raw): ?array
    {
        if (! $raw || trim($raw) === '') {
            return null;
        }

        $keywords = array_values(array_filter(
            array_map('trim', explode(',', $raw)),
            fn ($k) => $k !== ''
        ));

        return $keywords ?: null;
    }
}
