<?php

use App\Http\Controllers\Api\Admin\AdminDashboardController;
use App\Http\Controllers\Api\Admin\AdminPlanController;
use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\Admin\AdminWebhookController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Client\ClientDashboardController;
use App\Http\Controllers\Api\Facebook\FacebookConnectionController;
use App\Http\Controllers\Api\Facebook\FacebookWebhookController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\UserUsageController;
use App\Http\Controllers\Client\PaymentController;
use App\Http\Controllers\Client\SslCommerzController;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\Route;

// Public — external services call these without auth
Route::prefix('webhooks')->middleware('throttle:120,1')->group(function () {
    Route::get('facebook', [FacebookWebhookController::class, 'verify'])->name('api.webhooks.facebook.verify');
    Route::post('facebook', [FacebookWebhookController::class, 'receive'])->name('api.webhooks.facebook.receive');
    Route::post('stripe', [PaymentController::class, 'webhook'])->name('api.webhooks.stripe');
    Route::post('sslcommerz/ipn', [SslCommerzController::class, 'ipn'])->name('api.webhooks.sslcommerz.ipn');
});

Route::get('health', function () {
    return ApiResponse::success([
        'status' => 'ok',
        'application' => config('app.name'),
        'environment' => app()->environment(),
    ], 'Service is healthy');
})->name('api.health');

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->middleware(['guest', 'throttle:10,1'])->name('api.auth.register');
    Route::post('login', [AuthController::class, 'login'])->middleware(['guest', 'throttle:10,1'])->name('api.auth.login');
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware(['guest', 'throttle:5,1'])->name('api.auth.forgot-password');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware(['guest', 'throttle:5,1'])->name('api.auth.reset-password');

    Route::middleware(['auth:sanctum', 'account.active'])->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('api.auth.logout');
        Route::get('me', [AuthController::class, 'me'])->name('api.auth.me');
        Route::put('profile', [AuthController::class, 'profile'])->name('api.auth.profile');
        Route::put('change-password', [AuthController::class, 'changePassword'])->name('api.auth.change-password');
    });
});

Route::get('plans', [PlanController::class, 'index'])->name('api.plans.index');

Route::prefix('admin')->middleware(['auth:sanctum', 'account.active', 'role:super-admin'])->group(function () {
    Route::get('dashboard', [AdminDashboardController::class, 'index'])->name('api.admin.dashboard');

    Route::get('users', [AdminUserController::class, 'index'])->name('api.admin.users.index');
    Route::get('users/{user}', [AdminUserController::class, 'show'])->name('api.admin.users.show');
    Route::post('users/{user}/suspend', [AdminUserController::class, 'suspend'])->name('api.admin.users.suspend');
    Route::post('users/{user}/activate', [AdminUserController::class, 'activate'])->name('api.admin.users.activate');
    Route::post('users/{user}/assign-plan', [AdminUserController::class, 'assignPlan'])->name('api.admin.users.assign-plan');
    Route::post('users/{user}/extend-limit', [AdminUserController::class, 'extendLimit'])->name('api.admin.users.extend-limit');
    Route::get('users/{user}/usage-history', [AdminUserController::class, 'usageHistory'])->name('api.admin.users.usage-history');

    Route::get('plans', [AdminPlanController::class, 'index'])->name('api.admin.plans.index');
    Route::post('plans', [AdminPlanController::class, 'store'])->name('api.admin.plans.store');
    Route::put('plans/{plan}', [AdminPlanController::class, 'update'])->name('api.admin.plans.update');
    Route::delete('plans/{plan}', [AdminPlanController::class, 'destroy'])->name('api.admin.plans.destroy');

    Route::get('webhooks', [AdminWebhookController::class, 'index'])->name('api.admin.webhooks.index');
    Route::get('webhooks/{webhookEvent}', [AdminWebhookController::class, 'show'])->name('api.admin.webhooks.show');
    Route::post('webhooks/{webhookEvent}/retry', [AdminWebhookController::class, 'retry'])->name('api.admin.webhooks.retry');
});

Route::prefix('user')->middleware(['auth:sanctum', 'account.active'])->group(function () {
    Route::get('subscription', [UserUsageController::class, 'subscription'])->name('api.user.subscription');
    Route::get('usage', [UserUsageController::class, 'usage'])->name('api.user.usage');
    Route::get('usage/history', [UserUsageController::class, 'history'])->name('api.user.usage.history');
});

Route::prefix('facebook')->middleware(['auth:sanctum', 'account.active'])->group(function () {
    Route::get('connect-url', [FacebookConnectionController::class, 'connectUrl'])->name('api.facebook.connect-url');
    Route::get('pages', [FacebookConnectionController::class, 'pages'])->name('api.facebook.pages');
    Route::post('pages/{page}/connect', [FacebookConnectionController::class, 'connectPage'])->name('api.facebook.pages.connect');
    Route::post('pages/{page}/disconnect', [FacebookConnectionController::class, 'disconnectPage'])->name('api.facebook.pages.disconnect');
    Route::post('pages/{page}/enable-automation', [FacebookConnectionController::class, 'enableAutomation'])->name('api.facebook.pages.enable-automation');
    Route::post('pages/{page}/disable-automation', [FacebookConnectionController::class, 'disableAutomation'])->name('api.facebook.pages.disable-automation');
});

Route::prefix('dashboard')->middleware(['auth:sanctum', 'account.active'])->group(function () {
    Route::get('/', [ClientDashboardController::class, 'index'])->name('api.dashboard');
    Route::get('alerts', [ClientDashboardController::class, 'alerts'])->name('api.dashboard.alerts');
    Route::get('recent-activity', [ClientDashboardController::class, 'recentActivity'])->name('api.dashboard.recent-activity');
});
