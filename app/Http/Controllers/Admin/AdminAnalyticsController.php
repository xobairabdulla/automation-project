<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminAnalyticsController extends Controller
{
    public function index(AnalyticsService $analyticsService): \Inertia\Response
    {
        return Inertia::render('admin/analytics', [
            'stats' => Inertia::defer(fn () => $analyticsService->adminStats()),
        ]);
    }
}
