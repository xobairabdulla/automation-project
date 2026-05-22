<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AnalyticsController extends Controller
{
    public function index(Request $request): \Inertia\Response
    {
        $user = $request->user();
        $from = $request->input('from', now()->subDays(29)->toDateString());
        $to = $request->input('to', now()->toDateString());

        return Inertia::render('client/analytics', [
            'from' => $from,
            'to' => $to,
            'stats' => Inertia::defer(fn () => app(AnalyticsService::class)->clientStats($user, $from, $to)),
        ]);
    }

    public function stats(Request $request, AnalyticsService $analyticsService): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $stats = $analyticsService->clientStats($request->user(), $validated['from'], $validated['to']);

        return response()->json($stats);
    }
}
