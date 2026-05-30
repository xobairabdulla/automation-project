<?php

namespace App\Services;

use App\Models\ProductSource;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProductSourceService
{
    public function getActiveSourceForUser(int $userId): ?ProductSource
    {
        return ProductSource::where('user_id', $userId)
            ->where('is_active', true)
            ->where('product_image_reply_enabled', true)
            ->first();
    }

    /**
     * Test connectivity of a product source without persisting.
     *
     * @return array{ok: bool, message: string, product_count?: int}
     */
    public function testConnection(ProductSource $source): array
    {
        try {
            return match ($source->source_type) {
                'woocommerce_api' => $this->testWooCommerce($source),
                'shopify_api' => $this->testShopify($source),
                'custom_api' => $this->testCustomApi($source),
                'manual' => ['ok' => true, 'message' => 'Manual source — no connection needed.'],
                default => ['ok' => false, 'message' => 'Unknown source type.'],
            };
        } catch (\Throwable $e) {
            Log::warning('ProductSourceService: testConnection exception', ['error' => $e->getMessage()]);

            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    private function testWooCommerce(ProductSource $source): array
    {
        if (! $source->api_url) {
            return ['ok' => false, 'message' => 'API URL is required for WooCommerce.'];
        }

        $base = rtrim($source->api_url, '/');
        $response = Http::withBasicAuth($source->api_key ?? '', $source->api_secret ?? '')
            ->timeout(15)
            ->get("{$base}/wp-json/wc/v3/products", ['per_page' => 1]);

        if ($response->failed()) {
            $error = $response->json('message', $response->body());

            return ['ok' => false, 'message' => "WooCommerce error: {$error}"];
        }

        $total = (int) ($response->header('X-WP-Total') ?? 0);

        return ['ok' => true, 'message' => 'Connected.', 'product_count' => $total];
    }

    private function testShopify(ProductSource $source): array
    {
        if (! $source->api_url) {
            return ['ok' => false, 'message' => 'API URL (store URL) is required for Shopify.'];
        }

        $base = rtrim($source->api_url, '/');
        $response = Http::withHeaders(['X-Shopify-Access-Token' => $source->api_key ?? ''])
            ->timeout(15)
            ->get("{$base}/products/count.json");

        if ($response->failed()) {
            return ['ok' => false, 'message' => 'Shopify connection failed. Check your store URL and access token.'];
        }

        $count = $response->json('count', 0);

        return ['ok' => true, 'message' => 'Connected.', 'product_count' => $count];
    }

    private function testCustomApi(ProductSource $source): array
    {
        if (! $source->api_url) {
            return ['ok' => false, 'message' => 'API URL is required.'];
        }

        $headers = [];
        if ($source->api_key) {
            $headers['Authorization'] = 'Bearer '.$source->api_key;
        }

        $response = Http::withHeaders($headers)->timeout(15)->get($source->api_url);

        if ($response->failed()) {
            return ['ok' => false, 'message' => "API returned HTTP {$response->status()}."];
        }

        return ['ok' => true, 'message' => 'Connected and API responded successfully.'];
    }
}
