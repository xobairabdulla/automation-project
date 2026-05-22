<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UsageLimitService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserUsageController extends Controller
{
    public function subscription(Request $request, UsageLimitService $usageLimitService): JsonResponse
    {
        $subscription = $usageLimitService->currentSubscription($request->user())->load('plan');

        return ApiResponse::success($subscription, 'Subscription loaded');
    }

    public function usage(Request $request, UsageLimitService $usageLimitService): JsonResponse
    {
        return ApiResponse::success(
            $usageLimitService->currentUsageLimit($request->user()),
            'Usage limits loaded'
        );
    }

    public function history(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $request->user()->usageLogs()->latest()->limit(50)->get(),
            'Usage history loaded'
        );
    }
}
