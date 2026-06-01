<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ConversationTag;
use App\Models\Customer;
use App\Models\FacebookPage;
use App\Models\InternalNote;
use App\Services\ConversationService;
use App\Services\Facebook\FacebookUserProfileService;
use App\Services\FacebookMessageService;
use App\Services\UsageLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InboxController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $pages = FacebookPage::where('user_id', $user->id)
            ->where('is_connected', true)
            ->get(['id', 'page_name']);

        $tags = ConversationTag::where('tenant_id', $user->id)->get();

        return Inertia::render('client/inbox', [
            'pages' => $pages,
            'tags' => $tags,
        ]);
    }

    public function conversations(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Conversation::where('tenant_id', $user->effectiveTenantId())
            ->with(['customer', 'facebookPage:id,page_name', 'tags:id,name,color', 'messages' => function ($q) {
                $q->latest()->limit(1);
            }]);

        if ($request->filled('page_id')) {
            $query->where('facebook_page_id', $request->query('page_id'));
        }

        $filter = $request->query('filter', 'all');
        match ($filter) {
            'unread' => $query->where('is_read', false),
            'open' => $query->where('status', 'open'),
            'pending' => $query->where('status', 'pending'),
            'human_needed' => $query->where('human_takeover', true),
            'assigned' => $query->where('assigned_to', $user->id),
            'closed' => $query->where('status', 'closed'),
            default => null,
        };

        if ($request->filled('search')) {
            $search = '%'.$request->query('search').'%';
            $query->whereHas('customer', fn ($q) => $q->where('name', 'like', $search)
                ->orWhere('external_customer_id', 'like', $search));
        }

        $conversations = $query->orderByDesc('last_customer_message_at')->paginate(30);

        return response()->json($conversations);
    }

    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        abort_if($conversation->tenant_id !== $request->user()->effectiveTenantId(), 403);

        $conversation->load([
            'customer',
            'facebookPage:id,page_name',
            'tags:id,name,color',
            'messages' => fn ($q) => $q->orderBy('created_at'),
            'notes.user:id,name',
            'assignments.assignedTo:id,name',
        ]);

        return response()->json($conversation);
    }

    public function reply(Request $request, Conversation $conversation, FacebookMessageService $messageService, UsageLimitService $usageLimitService): JsonResponse
    {
        $user = $request->user();
        abort_if($conversation->tenant_id !== $user->effectiveTenantId(), 403);

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $customer = Customer::findOrFail($conversation->customer_id);

        $sent = $messageService->sendManualReply(
            $conversation->facebookPage,
            $conversation,
            $customer->external_customer_id,
            $validated['message'],
            $user,
        );

        $usageLimitService->incrementUsage($user, 'manual_reply', 1, [
            'conversation_id' => $conversation->id,
        ]);

        $conversation->update(['is_read' => true]);

        return response()->json(['sent' => $sent]);
    }

    public function assign(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();
        abort_if($conversation->tenant_id !== $user->effectiveTenantId(), 403);

        $validated = $request->validate([
            'assigned_to' => ['nullable', 'exists:users,id'],
        ]);

        $conversation->update(['assigned_to' => $validated['assigned_to']]);

        if ($validated['assigned_to']) {
            $conversation->assignments()->create([
                'tenant_id' => $user->id,
                'assigned_to' => $validated['assigned_to'],
                'assigned_by' => $user->id,
            ]);
        }

        return response()->json(['ok' => true]);
    }

    public function close(Request $request, Conversation $conversation): JsonResponse
    {
        abort_if($conversation->tenant_id !== $request->user()->effectiveTenantId(), 403);
        $conversation->update(['status' => 'closed', 'is_read' => true]);

        return response()->json(['ok' => true]);
    }

    public function markRead(Request $request, Conversation $conversation): JsonResponse
    {
        abort_if($conversation->tenant_id !== $request->user()->effectiveTenantId(), 403);
        $conversation->update(['is_read' => true]);

        return response()->json(['ok' => true]);
    }

    public function storeNote(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();
        abort_if($conversation->tenant_id !== $user->effectiveTenantId(), 403);

        $validated = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
        ]);

        $note = InternalNote::create([
            'tenant_id' => $user->id,
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'note' => $validated['note'],
        ]);

        $note->load('user:id,name');

        return response()->json($note);
    }

    public function updateTags(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();
        abort_if($conversation->tenant_id !== $user->effectiveTenantId(), 403);

        $validated = $request->validate([
            'tag_ids' => ['array'],
            'tag_ids.*' => ['exists:conversation_tags,id'],
        ]);

        $conversation->tags()->sync($validated['tag_ids'] ?? []);

        return response()->json(['ok' => true]);
    }

    public function notifications(Request $request): JsonResponse
    {
        $user = $request->user();
        $notifications = $user->unreadNotifications()->latest()->limit(20)->get();

        return response()->json($notifications);
    }

    public function refreshProfile(Request $request, Conversation $conversation, ConversationService $conversationService, FacebookUserProfileService $profileService): JsonResponse
    {
        abort_if($conversation->tenant_id !== $request->user()->effectiveTenantId(), 403);

        $customer = $conversation->customer;
        $page = $conversation->facebookPage;

        $profileService->bustCache($customer->external_customer_id);
        $conversationService->syncCustomerProfile($customer, $page, $profileService);

        return response()->json(['customer' => $customer->refresh()]);
    }

    public function storeTags(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $tag = ConversationTag::create([
            'tenant_id' => $user->id,
            'name' => $validated['name'],
            'color' => $validated['color'] ?? '#6366f1',
        ]);

        return response()->json($tag);
    }
}
