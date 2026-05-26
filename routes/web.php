<?php

use App\Http\Controllers\Admin\AdminAiLogController;
use App\Http\Controllers\Admin\AdminAnalyticsController;
use App\Http\Controllers\Admin\AdminAuditLogController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminEmailLogController;
use App\Http\Controllers\Admin\AdminPaymentController;
use App\Http\Controllers\Admin\AdminPlanController;
use App\Http\Controllers\Admin\AdminSystemSettingController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminWebhookController;
use App\Http\Controllers\Client\AiSettingsController;
use App\Http\Controllers\Client\AnalyticsController;
use App\Http\Controllers\Client\AutomationRuleController;
use App\Http\Controllers\Client\ClientDashboardController;
use App\Http\Controllers\Client\CommentsController;
use App\Http\Controllers\Client\HumanHandoverController;
use App\Http\Controllers\Client\InboxController;
use App\Http\Controllers\Client\KnowledgeBaseController;
use App\Http\Controllers\Client\NotificationController;
use App\Http\Controllers\Client\PaymentController;
use App\Http\Controllers\Client\ReplyTemplateController;
use App\Http\Controllers\Client\SslCommerzController;
use App\Http\Controllers\Client\TeamController;
use App\Http\Controllers\Facebook\FacebookPageController;
use App\Http\Controllers\GuideController;
use App\Services\UsageLimitService;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

// Team invitation acceptance — no auth required
Route::get('/team/accept/{invitation:token}', [TeamController::class, 'showAccept'])->name('team.accept.show');
Route::post('/team/accept/{invitation:token}', [TeamController::class, 'accept'])->name('team.accept');

// SSLCommerz callbacks — POSTed by SSLCommerz gateway, no auth, no CSRF
Route::prefix('billing/sslcommerz')->name('billing.sslcommerz.')->withoutMiddleware([VerifyCsrfToken::class])->group(function () {
    Route::post('success', [SslCommerzController::class, 'success'])->name('success');
    Route::post('fail', [SslCommerzController::class, 'fail'])->name('fail');
    Route::post('cancel', [SslCommerzController::class, 'cancel'])->name('cancel');
});

Route::middleware(['auth', 'account.active'])->group(function () {
    Route::get('dashboard', [ClientDashboardController::class, 'index'])
        ->middleware('role:client-admin|agent|support-admin|super-admin')
        ->name('dashboard');

    Route::get('guide', [GuideController::class, 'index'])
        ->middleware('role:client-admin|agent|support-admin|super-admin')
        ->name('guide');

    Route::get('usage-limits', function (UsageLimitService $usageLimitService) {
        $user = request()->user();

        return Inertia::render('client/usage-limits', [
            'subscription' => $usageLimitService->currentSubscription($user)->load('plan'),
            'usage' => $usageLimitService->currentUsageLimit($user),
            'history' => $user->usageLogs()->latest()->limit(20)->get(),
        ]);
    })->middleware('role:client-admin|agent|support-admin|super-admin')->name('usage-limits');

    Route::prefix('inbox')->name('inbox.')->group(function () {
        Route::get('/', [InboxController::class, 'index'])->name('index');
        Route::get('/conversations', [InboxController::class, 'conversations'])->name('conversations');
        Route::get('/conversations/{conversation}', [InboxController::class, 'show'])->name('show');
        Route::post('/conversations/{conversation}/reply', [InboxController::class, 'reply'])->name('reply');
        Route::post('/conversations/{conversation}/assign', [InboxController::class, 'assign'])->name('assign');
        Route::post('/conversations/{conversation}/close', [InboxController::class, 'close'])->name('close');
        Route::post('/conversations/{conversation}/read', [InboxController::class, 'markRead'])->name('read');
        Route::post('/conversations/{conversation}/notes', [InboxController::class, 'storeNote'])->name('notes.store');
        Route::post('/conversations/{conversation}/tags', [InboxController::class, 'updateTags'])->name('tags.update');
        Route::post('/tags', [InboxController::class, 'storeTags'])->name('tags.store');
        Route::post('/conversations/{conversation}/human-takeover/enable', [HumanHandoverController::class, 'enable'])->name('human-takeover.enable');
        Route::post('/conversations/{conversation}/human-takeover/disable', [HumanHandoverController::class, 'disable'])->name('human-takeover.disable');
        Route::get('/notifications', [InboxController::class, 'notifications'])->name('notifications');
    });

    Route::prefix('team')->name('team.')->group(function () {
        Route::get('/', [TeamController::class, 'index'])->name('index');
        Route::post('/invite', [TeamController::class, 'invite'])->name('invite');
        Route::delete('/invitations/{invitation}', [TeamController::class, 'revokeInvitation'])->name('invitations.revoke');
        Route::delete('/members/{member}', [TeamController::class, 'removeMember'])->name('members.remove');
    });

    Route::get('comments', [CommentsController::class, 'index'])->name('comments.index');

    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::post('/{id}/read', [NotificationController::class, 'markRead'])->name('read');
        Route::post('/read-all', [NotificationController::class, 'markAllRead'])->name('read-all');
        Route::delete('/{id}', [NotificationController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('analytics')->name('analytics.')->group(function () {
        Route::get('/', [AnalyticsController::class, 'index'])->name('index');
        Route::get('/stats', [AnalyticsController::class, 'stats'])->name('stats');
    });

    Route::prefix('billing')->name('billing.')->group(function () {
        Route::get('/', [PaymentController::class, 'index'])->name('index');
        Route::post('/checkout/{plan}', [PaymentController::class, 'checkout'])->name('checkout');
        Route::post('/extend-checkout/{package}', [PaymentController::class, 'extendCheckout'])->name('extend-checkout');
        Route::get('/success', [PaymentController::class, 'success'])->name('success');
        Route::get('/cancel', [PaymentController::class, 'cancel'])->name('cancel');
        Route::get('/history', [PaymentController::class, 'history'])->name('history');

    });

    Route::prefix('knowledge-base')->name('knowledge-base.')->group(function () {
        Route::get('/', [KnowledgeBaseController::class, 'index'])->name('index');
        Route::post('/bases', [KnowledgeBaseController::class, 'storeBase'])->name('bases.store');
        Route::post('/items', [KnowledgeBaseController::class, 'storeItem'])->name('items.store');
        Route::put('/items/{knowledgeBaseItem}', [KnowledgeBaseController::class, 'updateItem'])->name('items.update');
        Route::delete('/items/{knowledgeBaseItem}', [KnowledgeBaseController::class, 'destroyItem'])->name('items.destroy');
    });

    Route::prefix('ai-settings')->name('ai-settings.')->group(function () {
        Route::get('/', [AiSettingsController::class, 'index'])->name('index');
        Route::post('/', [AiSettingsController::class, 'upsert'])->name('upsert');
        Route::post('/test-reply', [AiSettingsController::class, 'testReply'])->name('test-reply');
    });

    Route::prefix('automation-rules')->name('automation-rules.')->group(function () {
        Route::get('/', [AutomationRuleController::class, 'index'])->name('index');
        Route::post('/', [AutomationRuleController::class, 'store'])->name('store');
        Route::put('{automationRule}', [AutomationRuleController::class, 'update'])->name('update');
        Route::delete('{automationRule}', [AutomationRuleController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('reply-templates')->name('reply-templates.')->group(function () {
        Route::get('/', [ReplyTemplateController::class, 'index'])->name('index');
        Route::post('/', [ReplyTemplateController::class, 'store'])->name('store');
        Route::put('{replyTemplate}', [ReplyTemplateController::class, 'update'])->name('update');
        Route::delete('{replyTemplate}', [ReplyTemplateController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('facebook')->name('facebook.')->group(function () {
        Route::get('pages', [FacebookPageController::class, 'index'])->name('pages');
        Route::get('connect', [FacebookPageController::class, 'connect'])->name('connect');
        Route::get('callback', [FacebookPageController::class, 'callback'])->name('callback');
        Route::post('pages/{page}/connect', [FacebookPageController::class, 'connectPage'])->name('pages.connect');
        Route::post('pages/{page}/disconnect', [FacebookPageController::class, 'disconnectPage'])->name('pages.disconnect');
        Route::post('pages/{page}/enable-automation', [FacebookPageController::class, 'enableAutomation'])->name('pages.enable-automation');
        Route::post('pages/{page}/disable-automation', [FacebookPageController::class, 'disableAutomation'])->name('pages.disable-automation');
    });

    Route::middleware('role:super-admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

        Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
        Route::post('users', [AdminUserController::class, 'store'])->name('users.store');
        Route::get('users/{user}', [AdminUserController::class, 'show'])->name('users.show');
        Route::post('users/{user}/suspend', [AdminUserController::class, 'suspend'])->name('users.suspend');
        Route::post('users/{user}/activate', [AdminUserController::class, 'activate'])->name('users.activate');
        Route::post('users/{user}/assign-plan', [AdminUserController::class, 'assignPlan'])->name('users.assign-plan');
        Route::post('users/{user}/extend-limit', [AdminUserController::class, 'extendLimit'])->name('users.extend-limit');

        Route::get('plans', [AdminPlanController::class, 'index'])->name('plans.index');
        Route::post('plans', [AdminPlanController::class, 'store'])->name('plans.store');
        Route::put('plans/{plan}', [AdminPlanController::class, 'update'])->name('plans.update');
        Route::post('plans/{plan}/deactivate', [AdminPlanController::class, 'deactivate'])->name('plans.deactivate');

        Route::get('webhook-logs', [AdminWebhookController::class, 'index'])->name('webhook-logs.index');
        Route::get('webhook-logs/{webhookEvent}', [AdminWebhookController::class, 'show'])->name('webhook-logs.show');
        Route::post('webhook-logs/{webhookEvent}/retry', [AdminWebhookController::class, 'retry'])->name('webhook-logs.retry');

        Route::get('analytics', [AdminAnalyticsController::class, 'index'])->name('analytics.index');
        Route::get('audit-logs', [AdminAuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('email-logs', [AdminEmailLogController::class, 'index'])->name('email-logs.index');

        Route::get('payments', [AdminPaymentController::class, 'index'])->name('payments.index');

        Route::get('ai-logs', [AdminAiLogController::class, 'index'])->name('ai-logs.index');

        Route::get('system-settings', [AdminSystemSettingController::class, 'index'])->name('system-settings.index');
        Route::post('system-settings', [AdminSystemSettingController::class, 'upsert'])->name('system-settings.upsert');
        Route::delete('system-settings/{systemSetting}', [AdminSystemSettingController::class, 'destroy'])->name('system-settings.destroy');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
