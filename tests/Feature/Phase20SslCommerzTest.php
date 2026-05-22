<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use App\Services\SslCommerzService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class Phase20SslCommerzTest extends TestCase
{
    use RefreshDatabase;

    // ── Config ──────────────────────────────────────────────────────────────

    public function test_payment_gateway_defaults_to_stripe(): void
    {
        $this->assertEquals('stripe', config('services.payments.gateway'));
    }

    public function test_sslcommerz_config_keys_exist(): void
    {
        $this->assertArrayHasKey('store_id', config('services.sslcommerz'));
        $this->assertArrayHasKey('store_password', config('services.sslcommerz'));
        $this->assertArrayHasKey('sandbox', config('services.sslcommerz'));
        $this->assertArrayHasKey('currency', config('services.sslcommerz'));
    }

    // ── Gateway routing ──────────────────────────────────────────────────────

    public function test_checkout_uses_stripe_when_gateway_is_stripe(): void
    {
        config(['services.payments.gateway' => 'stripe']);
        config(['services.payments.stripe_secret' => 'sk_test_fake']);

        $user = User::factory()->create();
        $plan = Plan::factory()->create([
            'stripe_monthly_price_id' => 'price_test_123',
            'monthly_price' => 10,
        ]);

        Http::fake([
            'api.stripe.com/*' => Http::response(['url' => 'https://checkout.stripe.com/pay/test', 'id' => 'cs_test_123'], 200),
        ]);

        $this->actingAs($user)
            ->post(route('billing.checkout', $plan), ['billing_cycle' => 'monthly'])
            ->assertRedirect('https://checkout.stripe.com/pay/test');
    }

    public function test_checkout_uses_sslcommerz_when_gateway_is_sslcommerz(): void
    {
        config(['services.payments.gateway' => 'sslcommerz']);
        config(['services.sslcommerz.store_id' => 'test_store']);
        config(['services.sslcommerz.store_password' => 'test_pass']);

        $user = User::factory()->create();
        $plan = Plan::factory()->create(['monthly_price' => 500]);

        Http::fake([
            'sandbox.sslcommerz.com/*' => Http::response([
                'status' => 'SUCCESS',
                'GatewayPageURL' => 'https://sandbox.sslcommerz.com/gwprocess/v4/pay/test',
                'sessionkey' => 'ssl_session_123',
            ], 200),
        ]);

        $this->actingAs($user)
            ->post(route('billing.checkout', $plan), ['billing_cycle' => 'monthly'])
            ->assertRedirect('https://sandbox.sslcommerz.com/gwprocess/v4/pay/test');
    }

    // ── SslCommerzService unit ────────────────────────────────────────────────

    public function test_initiate_payment_throws_on_gateway_error(): void
    {
        config(['services.sslcommerz.store_id' => 'test_store']);
        config(['services.sslcommerz.store_password' => 'test_pass']);

        Http::fake([
            'sandbox.sslcommerz.com/*' => Http::response(['status' => 'FAILED', 'failedreason' => 'Invalid credentials'], 200),
        ]);

        $service = app(SslCommerzService::class);
        $user = User::factory()->create();
        $plan = Plan::factory()->create(['monthly_price' => 200]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/SSLCommerz initiation failed/');

        $service->createSubscriptionCheckout($user, $plan, 'monthly');
    }

    public function test_initiate_payment_throws_on_http_error(): void
    {
        config(['services.sslcommerz.store_id' => 'test_store']);
        config(['services.sslcommerz.store_password' => 'test_pass']);

        Http::fake([
            'sandbox.sslcommerz.com/*' => Http::response('Server Error', 500),
        ]);

        $service = app(SslCommerzService::class);
        $user = User::factory()->create();
        $plan = Plan::factory()->create(['monthly_price' => 200]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/SSLCommerz gateway error/');

        $service->createSubscriptionCheckout($user, $plan, 'monthly');
    }

    // ── SSLCommerz callbacks ─────────────────────────────────────────────────

    public function test_sslcommerz_success_callback_completes_payment(): void
    {
        config(['services.sslcommerz.store_password' => 'test_pass']);

        $user = User::factory()->create();
        $plan = Plan::factory()->create(['monthly_price' => 500]);

        $payment = Payment::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
            'type' => 'subscription',
            'metadata_json' => ['plan_id' => $plan->id, 'billing_cycle' => 'monthly'],
        ]);

        $postData = $this->buildSslCommerzPostData($payment->id, 'test_pass');

        $this->post(route('billing.sslcommerz.success'), $postData)
            ->assertOk();

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'completed',
        ]);
    }

    public function test_sslcommerz_success_callback_rejects_invalid_hash(): void
    {
        config(['services.sslcommerz.store_password' => 'real_pass']);

        $user = User::factory()->create();
        $payment = Payment::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
            'type' => 'subscription',
        ]);

        $postData = $this->buildSslCommerzPostData($payment->id, 'wrong_pass');

        $this->post(route('billing.sslcommerz.success'), $postData)
            ->assertOk();

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'pending',
        ]);
    }

    public function test_sslcommerz_fail_callback_marks_payment_failed(): void
    {
        $user = User::factory()->create();
        $payment = Payment::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
            'type' => 'subscription',
        ]);

        $this->post(route('billing.sslcommerz.fail'), ['value_c' => $payment->id])
            ->assertOk();

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'failed',
        ]);
    }

    public function test_sslcommerz_cancel_callback_marks_payment_failed(): void
    {
        $user = User::factory()->create();
        $payment = Payment::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
            'type' => 'subscription',
        ]);

        $this->post(route('billing.sslcommerz.cancel'), ['value_c' => $payment->id])
            ->assertOk();

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'failed',
        ]);
    }

    // ── IPN endpoint ─────────────────────────────────────────────────────────

    public function test_sslcommerz_ipn_validates_and_completes_payment(): void
    {
        config(['services.sslcommerz.store_id' => 'test_store']);
        config(['services.sslcommerz.store_password' => 'test_pass']);

        $user = User::factory()->create();
        $plan = Plan::factory()->create(['monthly_price' => 500]);
        $payment = Payment::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
            'type' => 'subscription',
            'metadata_json' => ['plan_id' => $plan->id, 'billing_cycle' => 'monthly'],
        ]);

        Http::fake([
            'sandbox.sslcommerz.com/validator/*' => Http::response([
                'status' => 'VALID',
                'val_id' => 'val_123',
                'amount' => 500,
            ], 200),
        ]);

        $this->postJson(route('api.webhooks.sslcommerz.ipn'), [
            'val_id' => 'val_123',
            'value_c' => (string) $payment->id,
        ])->assertOk();

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'completed',
        ]);
    }

    public function test_sslcommerz_ipn_returns_400_on_invalid_validation(): void
    {
        config(['services.sslcommerz.store_id' => 'test_store']);
        config(['services.sslcommerz.store_password' => 'test_pass']);

        Http::fake([
            'sandbox.sslcommerz.com/validator/*' => Http::response(['status' => 'INVALID'], 200),
        ]);

        $this->postJson(route('api.webhooks.sslcommerz.ipn'), [
            'val_id' => 'bad_val',
            'value_c' => '999',
        ])->assertStatus(400);
    }

    // ── verify_hash logic ────────────────────────────────────────────────────

    public function test_verify_hash_returns_true_for_correct_hash(): void
    {
        config(['services.sslcommerz.store_password' => 'test_pass']);

        $service = app(SslCommerzService::class);

        $data = ['amount' => '500', 'currency' => 'BDT', 'value_c' => '1'];
        $keys = implode(',', array_keys($data));
        $preHash = '';
        foreach (array_keys($data) as $key) {
            $preHash .= $key.'='.$data[$key].'&';
        }
        $preHash .= 'store_passwd='.md5('test_pass');
        $hash = md5($preHash);

        $postData = array_merge($data, [
            'verify_key' => $keys,
            'verify_sign' => $hash,
        ]);

        $this->assertTrue($service->verifyHash($postData));
    }

    public function test_verify_hash_returns_false_for_wrong_hash(): void
    {
        config(['services.sslcommerz.store_password' => 'test_pass']);

        $service = app(SslCommerzService::class);

        $postData = [
            'amount' => '500',
            'verify_key' => 'amount',
            'verify_sign' => 'wrong_hash',
        ];

        $this->assertFalse($service->verifyHash($postData));
    }

    // ── Routes registered ────────────────────────────────────────────────────

    public function test_sslcommerz_callback_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('billing.sslcommerz.success'));
        $this->assertTrue(Route::has('billing.sslcommerz.fail'));
        $this->assertTrue(Route::has('billing.sslcommerz.cancel'));
        $this->assertTrue(Route::has('api.webhooks.sslcommerz.ipn'));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function buildSslCommerzPostData(int $paymentId, string $storePassword): array
    {
        $data = [
            'tran_id' => 'PAY-'.$paymentId.'-12345',
            'amount' => '500',
            'currency' => 'BDT',
            'value_c' => (string) $paymentId,
        ];

        $keys = implode(',', array_keys($data));
        $preHash = '';
        foreach (array_keys($data) as $key) {
            $preHash .= $key.'='.$data[$key].'&';
        }
        $preHash .= 'store_passwd='.md5($storePassword);
        $hash = md5($preHash);

        return array_merge($data, [
            'verify_key' => $keys,
            'verify_sign' => $hash,
        ]);
    }
}
