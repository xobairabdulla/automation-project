<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class PlanController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::success(
            Plan::query()
                ->where('status', 'active')
                ->orderBy('monthly_price')
                ->get(),
            'Plans loaded'
        );
    }
}
