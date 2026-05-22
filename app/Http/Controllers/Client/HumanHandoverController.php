<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Services\HumanHandoverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HumanHandoverController extends Controller
{
    public function enable(Request $request, Conversation $conversation, HumanHandoverService $handoverService): JsonResponse
    {
        $user = $request->user();
        abort_if($conversation->tenant_id !== $user->effectiveTenantId(), 403);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $handoverService->triggerHandover(
            $conversation,
            $validated['reason'] ?? 'manual',
            'agent',
            $user->id,
        );

        return response()->json(['ok' => true, 'conversation' => $conversation->fresh()]);
    }

    public function disable(Request $request, Conversation $conversation, HumanHandoverService $handoverService): JsonResponse
    {
        abort_if($conversation->tenant_id !== $request->user()->effectiveTenantId(), 403);

        $handoverService->releaseHandover($conversation);

        return response()->json(['ok' => true, 'conversation' => $conversation->fresh()]);
    }
}
