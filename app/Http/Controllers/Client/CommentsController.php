<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\FacebookComment;
use App\Models\FacebookPage;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CommentsController extends Controller
{
    public function index(Request $request): \Inertia\Response
    {
        $user = $request->user();
        $tenantId = $user->effectiveTenantId();

        $comments = FacebookComment::query()
            ->with('facebookPage:id,page_name', 'facebookPost:id,external_post_id', 'replies')
            ->where('tenant_id', $tenantId)
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->page_id, fn ($q, $v) => $q->where('facebook_page_id', $v))
            ->when($request->search, fn ($q, $v) => $q->where('comment_text', 'like', "%{$v}%"))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $pages = FacebookPage::where('user_id', $tenantId)
            ->get(['id', 'page_name'])
            ->map(fn (FacebookPage $page) => [
                'id' => $page->id,
                'name' => $page->page_name,
            ]);

        return Inertia::render('client/comments', [
            'comments' => $comments,
            'pages' => $pages,
            'filters' => $request->only('status', 'page_id', 'search'),
        ]);
    }
}
