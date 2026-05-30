<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\LimitExtensionPackage;
use App\Models\Payment;
use App\Models\Plan;
use App\Notifications\PaymentSuccessNotification;
use App\Services\SslCommerzService;
use App\Services\UsageLimitService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class SslCommerzController extends Controller
{
    public function success(Request $request, SslCommerzService $sslCommerz, UsageLimitService $usageLimitService): \Inertia\Response
    {
        $postData = $request->all();

        if (! $sslCommerz->verifyHash($postData)) {
            Log::warning('SSLCommerz success callback: invalid hash', ['data' => $postData]);

            return Inertia::render('client/billing-cancel');
        }

        $payment = Payment::find($postData['value_c'] ?? null);

        if ($payment && $payment->status === 'pending') {
            $this->fulfillPayment($payment, $usageLimitService);
        }

        return Inertia::render('client/billing-success', [
            'session_id' => $postData['tran_id'] ?? null,
        ]);
    }

    public function fail(Request $request): \Inertia\Response
    {
        $paymentId = $request->input('value_c');
        if ($paymentId) {
            Payment::where('id', $paymentId)->where('status', 'pending')->update(['status' => 'failed']);
        }

        return Inertia::render('client/billing-cancel');
    }

    public function cancel(Request $request): \Inertia\Response
    {
        $paymentId = $request->input('value_c');
        if ($paymentId) {
            Payment::where('id', $paymentId)->where('status', 'pending')->update(['status' => 'failed']);
        }

        return Inertia::render('client/billing-cancel');
    }

    public function ipn(Request $request, SslCommerzService $sslCommerz, UsageLimitService $usageLimitService): Response
    {
        $payment = $sslCommerz->verifyIpn($request->all());

        if (! $payment) {
            return response('IPN verification failed', 400);
        }

        if ($payment->status !== 'completed') {
            $this->fulfillPayment($payment, $usageLimitService);
        }

        return response('OK', 200);
    }

    private function fulfillPayment(Payment $payment, UsageLimitService $usageLimitService): void
    {
        $payment->update(['status' => 'completed', 'paid_at' => now()]);

        if ($payment->type === 'subscription') {
            $this->activateSubscription($payment);
        } elseif ($payment->type === 'limit_extension') {
            $this->extendLimits($payment, $usageLimitService);
        }

        $payment->fresh()?->user?->notify(new PaymentSuccessNotification($payment->fresh()));
    }

    private function activateSubscription(Payment $payment): void
    {
        $meta = $payment->metadata_json ?? [];
        $plan = Plan::find($meta['plan_id'] ?? null);
        $billingCycle = $meta['billing_cycle'] ?? 'monthly';

        if (! $plan) {
            return;
        }

        $user = $payment->user;
        $user->subscriptions()->whereIn('status', ['active', 'trial'])->update(['status' => 'expired']);

        $subscription = $user->subscriptions()->create([
            'tenant_id' => $user->tenant_id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'billing_cycle' => $billingCycle,
            'starts_at' => now(),
            'ends_at' => $billingCycle === 'yearly' ? now()->addYear() : now()->addMonth(),
            'next_billing_at' => $billingCycle === 'yearly' ? now()->addYear() : now()->addMonth(),
        ]);

        app(UsageLimitService::class)->createUsageLimitForSubscription($subscription);
        $payment->update(['subscription_id' => $subscription->id]);
    }

    private function extendLimits(Payment $payment, UsageLimitService $usageLimitService): void
    {
        $packageId = $payment->metadata_json['package_id'] ?? null;
        $package = $packageId ? LimitExtensionPackage::find($packageId) : null;

        if (! $package) {
            return;
        }

        $usageLimitService->fulfillPackagePurchase($payment->user, $package);
    }
}
