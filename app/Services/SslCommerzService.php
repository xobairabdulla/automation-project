<?php

namespace App\Services;

use App\Models\LimitExtensionPackage;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SslCommerzService
{
    private string $storeId;

    private string $storePassword;

    private bool $isSandbox;

    private string $baseUrl;

    public function __construct()
    {
        $this->storeId = config('services.sslcommerz.store_id') ?? '';
        $this->storePassword = config('services.sslcommerz.store_password') ?? '';
        $this->isSandbox = (bool) (config('services.sslcommerz.sandbox') ?? true);
        $this->baseUrl = $this->isSandbox
            ? 'https://sandbox.sslcommerz.com'
            : 'https://securepay.sslcommerz.com';
    }

    public function createSubscriptionCheckout(User $user, Plan $plan, string $billingCycle): string
    {
        $amount = $billingCycle === 'yearly' ? $plan->yearly_price : $plan->monthly_price;

        $payment = Payment::create([
            'user_id' => $user->id,
            'amount' => $amount,
            'currency' => config('services.sslcommerz.currency', 'BDT'),
            'status' => 'pending',
            'type' => 'subscription',
            'metadata_json' => ['plan_id' => $plan->id, 'billing_cycle' => $billingCycle],
        ]);

        return $this->initiatePayment($payment, $user, [
            'product_name' => $plan->name.' Plan ('.$billingCycle.')',
            'product_category' => 'subscription',
        ]);
    }

    public function createExtensionCheckout(User $user, LimitExtensionPackage $package): string
    {
        $payment = Payment::create([
            'user_id' => $user->id,
            'amount' => $package->price,
            'currency' => config('services.sslcommerz.currency', 'BDT'),
            'status' => 'pending',
            'type' => 'limit_extension',
            'metadata_json' => ['package_id' => $package->id],
        ]);

        return $this->initiatePayment($payment, $user, [
            'product_name' => $package->name.' Extension',
            'product_category' => 'limit_extension',
        ]);
    }

    /**
     * Verify IPN (Instant Payment Notification) from SSLCommerz.
     * Returns the validated payment or null if verification fails.
     */
    public function verifyIpn(array $ipnData): ?Payment
    {
        $valId = $ipnData['val_id'] ?? null;
        $paymentId = $ipnData['value_c'] ?? null;

        if (! $valId || ! $paymentId) {
            return null;
        }

        $response = Http::get("{$this->baseUrl}/validator/api/validationserverAPI.php", [
            'val_id' => $valId,
            'store_id' => $this->storeId,
            'store_passwd' => $this->storePassword,
            'format' => 'json',
        ]);

        if ($response->failed()) {
            Log::error('SSLCommerz IPN validation HTTP error', ['val_id' => $valId]);

            return null;
        }

        $result = $response->json();

        if (($result['status'] ?? '') !== 'VALID' && ($result['status'] ?? '') !== 'VALIDATED') {
            Log::warning('SSLCommerz IPN validation failed', ['status' => $result['status'] ?? 'unknown', 'val_id' => $valId]);

            return null;
        }

        $payment = Payment::find($paymentId);

        if (! $payment) {
            Log::error('SSLCommerz IPN: payment not found', ['payment_id' => $paymentId]);

            return null;
        }

        return $payment;
    }

    /**
     * Verify hash for success/cancel/fail callbacks (not IPN).
     * SSLCommerz sends verify_sign and verify_key — hash check prevents spoofing.
     */
    public function verifyHash(array $postData): bool
    {
        if (empty($postData['verify_sign']) || empty($postData['verify_key'])) {
            return false;
        }

        $keys = explode(',', $postData['verify_key']);
        $preHashString = '';

        foreach ($keys as $key) {
            $preHashString .= $key.'='.($postData[$key] ?? '').'&';
        }

        $preHashString .= 'store_passwd='.md5($this->storePassword);
        $hashValue = md5($preHashString);

        return hash_equals($hashValue, $postData['verify_sign']);
    }

    private function initiatePayment(Payment $payment, User $user, array $extra): string
    {
        $callbackBase = url('/billing/sslcommerz');

        $params = [
            'store_id' => $this->storeId,
            'store_passwd' => $this->storePassword,
            'total_amount' => $payment->amount,
            'currency' => $payment->currency,
            'tran_id' => 'PAY-'.$payment->id.'-'.time(),
            'success_url' => $callbackBase.'/success',
            'fail_url' => $callbackBase.'/fail',
            'cancel_url' => $callbackBase.'/cancel',
            'ipn_url' => url('/api/webhooks/sslcommerz/ipn'),
            'cus_name' => $user->name,
            'cus_email' => $user->email,
            'cus_phone' => '01XXXXXXXXX',
            'cus_add1' => 'N/A',
            'cus_city' => 'N/A',
            'cus_country' => 'Bangladesh',
            'product_name' => $extra['product_name'],
            'product_category' => $extra['product_category'],
            'product_profile' => 'non-physical-goods',
            'value_a' => 'automation',
            'value_b' => (string) $user->id,
            'value_c' => (string) $payment->id,
            'value_d' => $extra['product_category'],
            'shipping_method' => 'NO',
            'num_of_item' => 1,
            'weight_of_items' => 0,
            'logistic' => 'NO',
        ];

        $response = Http::asForm()
            ->post("{$this->baseUrl}/gwprocess/v4/api.php", $params);

        if ($response->failed()) {
            throw new RuntimeException('SSLCommerz gateway error: '.$response->body());
        }

        $data = $response->json();

        if (($data['status'] ?? '') !== 'SUCCESS') {
            throw new RuntimeException('SSLCommerz initiation failed: '.($data['failedreason'] ?? 'unknown'));
        }

        $gatewayUrl = $data['GatewayPageURL'] ?? null;

        if (! $gatewayUrl) {
            throw new RuntimeException('SSLCommerz did not return a gateway URL.');
        }

        $payment->update(['stripe_session_id' => $data['sessionkey'] ?? null]);

        return $gatewayUrl;
    }
}
