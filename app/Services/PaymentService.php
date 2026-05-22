<?php

namespace App\Services;

use App\Models\LimitExtensionPackage;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\PaymentSuccessNotification;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PaymentService
{
    private string $stripeSecret;

    private string $webhookSecret;

    public function __construct()
    {
        $this->stripeSecret = config('services.payments.stripe_secret', '');
        $this->webhookSecret = config('services.payments.stripe_webhook_secret', '');
    }

    public function createSubscriptionCheckout(User $user, Plan $plan, string $billingCycle): string
    {
        $priceId = $billingCycle === 'yearly' ? $plan->stripe_yearly_price_id : $plan->stripe_monthly_price_id;

        if (empty($priceId)) {
            throw new RuntimeException('No Stripe price ID configured for this plan and billing cycle.');
        }

        $payment = Payment::create([
            'user_id' => $user->id,
            'amount' => $billingCycle === 'yearly' ? $plan->yearly_price : $plan->monthly_price,
            'currency' => 'USD',
            'status' => 'pending',
            'type' => 'subscription',
            'metadata_json' => ['plan_id' => $plan->id, 'billing_cycle' => $billingCycle],
        ]);

        $session = $this->createCheckoutSession([
            'mode' => 'subscription',
            'line_items' => [['price' => $priceId, 'quantity' => 1]],
            'success_url' => url('/billing/success?session_id={CHECKOUT_SESSION_ID}'),
            'cancel_url' => url('/billing/cancel'),
            'client_reference_id' => (string) $payment->id,
            'metadata' => ['payment_id' => $payment->id, 'user_id' => $user->id, 'plan_id' => $plan->id, 'billing_cycle' => $billingCycle],
        ]);

        $payment->update(['stripe_session_id' => $session['id']]);

        return $session['url'];
    }

    public function createExtensionCheckout(User $user, LimitExtensionPackage $package): string
    {
        if (empty($package->stripe_price_id)) {
            throw new RuntimeException('No Stripe price ID configured for this package.');
        }

        $payment = Payment::create([
            'user_id' => $user->id,
            'amount' => $package->price,
            'currency' => 'USD',
            'status' => 'pending',
            'type' => 'limit_extension',
            'metadata_json' => ['package_id' => $package->id],
        ]);

        $session = $this->createCheckoutSession([
            'mode' => 'payment',
            'line_items' => [['price' => $package->stripe_price_id, 'quantity' => 1]],
            'success_url' => url('/billing/success?session_id={CHECKOUT_SESSION_ID}'),
            'cancel_url' => url('/billing/cancel'),
            'client_reference_id' => (string) $payment->id,
            'metadata' => ['payment_id' => $payment->id, 'user_id' => $user->id, 'package_id' => $package->id],
        ]);

        $payment->update(['stripe_session_id' => $session['id']]);

        return $session['url'];
    }

    public function handleWebhook(string $payload, string $signature): void
    {
        if (! $this->verifyWebhookSignature($payload, $signature)) {
            throw new RuntimeException('Invalid webhook signature.');
        }

        $event = json_decode($payload, true);

        match ($event['type'] ?? '') {
            'checkout.session.completed' => $this->handleCheckoutCompleted($event['data']['object']),
            default => null,
        };
    }

    public function fulfillSubscriptionPayment(Payment $payment, Subscription $subscription): void
    {
        $payment->update([
            'status' => 'completed',
            'subscription_id' => $subscription->id,
            'paid_at' => now(),
        ]);
    }

    public function fulfillExtensionPayment(Payment $payment, UsageLimitService $usageLimitService): void
    {
        $packageId = $payment->metadata_json['package_id'] ?? null;
        $package = $packageId ? LimitExtensionPackage::find($packageId) : null;

        if (! $package) {
            return;
        }

        $user = $payment->user;
        $usageLimit = $usageLimitService->currentUsageLimit($user);

        if ($package->message_extra > 0) {
            $usageLimit->increment('message_reply_limit', $package->message_extra);
        }

        if ($package->comment_extra > 0) {
            $usageLimit->increment('comment_reply_limit', $package->comment_extra);
        }

        if ($package->ai_extra > 0) {
            $usageLimit->increment('ai_reply_limit', $package->ai_extra);
        }

        $payment->update(['status' => 'completed', 'paid_at' => now()]);
    }

    public function recordAdminGrantedSubscription(User $admin, User $user, Subscription $subscription): Payment
    {
        return Payment::create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'amount' => 0,
            'currency' => 'USD',
            'status' => 'completed',
            'type' => 'admin_granted',
            'metadata_json' => ['granted_by' => $admin->id],
            'paid_at' => now(),
        ]);
    }

    private function handleCheckoutCompleted(array $session): void
    {
        $paymentId = $session['metadata']['payment_id'] ?? null;

        if (! $paymentId) {
            return;
        }

        $payment = Payment::find($paymentId);

        if (! $payment || $payment->status === 'completed') {
            return;
        }

        $payment->update([
            'stripe_payment_intent_id' => $session['payment_intent'] ?? null,
            'status' => 'completed',
            'paid_at' => now(),
        ]);

        if ($payment->type === 'subscription') {
            $this->activateSubscriptionAfterPayment($payment, $session);
        } elseif ($payment->type === 'limit_extension') {
            $this->fulfillExtensionPayment($payment, app(UsageLimitService::class));
        }

        $payment->fresh()->user?->notify(new PaymentSuccessNotification($payment->fresh()));
    }

    private function activateSubscriptionAfterPayment(Payment $payment, array $session): void
    {
        $meta = $payment->metadata_json ?? [];
        $planId = $meta['plan_id'] ?? null;
        $billingCycle = $meta['billing_cycle'] ?? 'monthly';

        if (! $planId) {
            return;
        }

        $user = $payment->user;
        $plan = Plan::find($planId);

        if (! $plan) {
            return;
        }

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

    private function createCheckoutSession(array $params): array
    {
        $response = Http::asForm()
            ->withHeaders(['Authorization' => 'Bearer '.$this->stripeSecret])
            ->post('https://api.stripe.com/v1/checkout/sessions', $this->flattenParams($params));

        if ($response->failed()) {
            throw new RuntimeException('Stripe checkout session creation failed: '.$response->body());
        }

        return $response->json();
    }

    private function verifyWebhookSignature(string $payload, string $signature): bool
    {
        if (empty($this->webhookSecret)) {
            return false;
        }

        // Parse Stripe signature header: t=timestamp,v1=hash
        $parts = [];
        foreach (explode(',', $signature) as $part) {
            [$k, $v] = array_pad(explode('=', $part, 2), 2, '');
            $parts[$k] = $v;
        }

        $timestamp = $parts['t'] ?? '';
        $expected = $parts['v1'] ?? '';

        if (empty($timestamp) || empty($expected)) {
            return false;
        }

        $signed = $timestamp.'.'.$payload;
        $computed = hash_hmac('sha256', $signed, $this->webhookSecret);

        return hash_equals($computed, $expected);
    }

    /**
     * Flatten nested arrays into Stripe's expected dot-notation form fields.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function flattenParams(array $params, string $prefix = ''): array
    {
        $result = [];

        foreach ($params as $key => $value) {
            $fullKey = $prefix !== '' ? "{$prefix}[{$key}]" : $key;

            if (is_array($value)) {
                $result = array_merge($result, $this->flattenParams($value, $fullKey));
            } else {
                $result[$fullKey] = $value;
            }
        }

        return $result;
    }
}
