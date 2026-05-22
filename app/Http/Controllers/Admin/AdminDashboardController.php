<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/dashboard', [
            'stats' => [
                'total_users' => User::count(),
                'active_users' => User::where('status', 'active')->count(),
                'suspended_users' => User::where('status', 'suspended')->count(),
                'pending_users' => User::where('status', 'pending')->count(),
                'total_plans' => Plan::count(),
                'active_subscriptions' => Subscription::whereIn('status', ['active', 'trial'])->count(),
            ],
        ]);
    }
}
