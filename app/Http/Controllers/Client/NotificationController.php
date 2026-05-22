<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NotificationController extends Controller
{
    public function index(Request $request): \Inertia\Response
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->paginate(20);

        return Inertia::render('client/notifications', [
            'notifications' => $notifications,
        ]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json(['ok' => true]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $request->user()->notifications()->findOrFail($id)->delete();

        return response()->json(['ok' => true]);
    }
}
