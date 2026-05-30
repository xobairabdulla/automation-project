<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\LimitExtensionPackage;
use App\Models\Payment;
use App\Models\Plan;
use App\Services\PaymentService;
use App\Services\SslCommerzService;
use App\Services\UsageLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use RuntimeException;

class PaymentController extends Controller
{
    public function index(Request $request, UsageLimitService $usageLimitService): \Inertia\Response
    {
        $user = $request->user();
        $usageLimit = $usageLimitService->currentUsageLimit($user);

        $packages = LimitExtensionPackage::where('is_active', true)
            ->orderBy('price')
            ->get();

        $payments = Payment::where('user_id', $user->id)
            ->latest()
            ->limit(20)
            ->get();

        return Inertia::render('client/billing', [
            'usageLimit' => $usageLimit,
            'packages' => $packages,
            'payments' => $payments,
        ]);
    }

    public function checkout(
        Request $request,
        Plan $plan,
        PaymentService $paymentService,
        SslCommerzService $sslCommerzService,
    ): RedirectResponse {
        $validated = $request->validate([
            'billing_cycle' => ['required', 'in:monthly,yearly'],
        ]);

        try {
            $url = config('services.payments.gateway') === 'sslcommerz'
                ? $sslCommerzService->createSubscriptionCheckout($request->user(), $plan, $validated['billing_cycle'])
                : $paymentService->createSubscriptionCheckout($request->user(), $plan, $validated['billing_cycle']);

            return redirect($url);
        } catch (RuntimeException $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }
    }

    public function extendCheckout(
        Request $request,
        LimitExtensionPackage $package,
        PaymentService $paymentService,
        SslCommerzService $sslCommerzService,
    ): RedirectResponse {
        try {
            $url = config('services.payments.gateway') === 'sslcommerz'
                ? $sslCommerzService->createExtensionCheckout($request->user(), $package)
                : $paymentService->createExtensionCheckout($request->user(), $package);

            return redirect($url);
        } catch (RuntimeException $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }
    }

    public function webhook(Request $request, PaymentService $paymentService): Response
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature', '');

        try {
            $paymentService->handleWebhook($payload, $signature);
        } catch (RuntimeException) {
            return response('Webhook error', 400);
        }

        return response('OK', 200);
    }

    public function success(Request $request): \Inertia\Response
    {
        return Inertia::render('client/billing-success', [
            'session_id' => $request->query('session_id'),
        ]);
    }

    public function cancel(): \Inertia\Response
    {
        return Inertia::render('client/billing-cancel');
    }

    public function history(Request $request): JsonResponse
    {
        $payments = Payment::where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return response()->json($payments);
    }
}
