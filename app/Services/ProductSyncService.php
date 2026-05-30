<?php

namespace App\Services;

use App\Models\ProductCache;
use App\Models\ProductSource;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProductSyncService
{
    /**
     * Sync all products for a given source and return the count upserted.
     */
    public function sync(ProductSource $source): int
    {
        return match ($source->source_type) {
            'woocommerce_api' => $this->syncWooCommerce($source),
            'shopify_api' => $this->syncShopify($source),
            'custom_api' => $this->syncCustomApi($source),
            'manual' => 0, // manual products are managed via the UI directly
            default => 0,
        };
    }

    private function syncWooCommerce(ProductSource $source): int
    {
        if (! $source->api_url) {
            Log::warning('ProductSyncService: WooCommerce source missing api_url', ['source_id' => $source->id]);

            return 0;
        }

        $base = rtrim($source->api_url, '/');
        $page = 1;
        $upserted = 0;

        do {
            $response = Http::withBasicAuth($source->api_key ?? '', $source->api_secret ?? '')
                ->timeout(30)
                ->get("{$base}/wp-json/wc/v3/products", [
                    'per_page' => 100,
                    'page' => $page,
                    'status' => 'publish',
                ]);

            if ($response->failed()) {
                Log::error('ProductSyncService: WooCommerce API error', [
                    'source_id' => $source->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                break;
            }

            $products = $response->json();

            if (empty($products)) {
                break;
            }

            foreach ($products as $product) {
                $images = collect($product['images'] ?? [])
                    ->pluck('src')
                    ->filter(fn ($url) => str_starts_with((string) $url, 'https://'))
                    ->values()
                    ->all();

                ProductCache::updateOrCreate(
                    ['source_id' => $source->id, 'external_product_id' => (string) $product['id']],
                    [
                        'user_id' => $source->user_id,
                        'product_name' => $product['name'] ?? 'Unknown',
                        'sku' => $product['sku'] ?? null,
                        'category' => collect($product['categories'] ?? [])->pluck('name')->implode(', ') ?: null,
                        'price' => is_numeric($product['price'] ?? null) ? (float) $product['price'] : null,
                        'product_url' => $product['permalink'] ?? null,
                        'image_urls' => $images,
                        'description' => strip_tags($product['short_description'] ?? $product['description'] ?? ''),
                        'stock_status' => $product['stock_status'] ?? null,
                        'keywords' => collect($product['tags'] ?? [])->pluck('name')->all(),
                        'last_synced_at' => now(),
                    ]
                );

                $upserted++;
            }

            $page++;
        } while (count($products) === 100);

        $source->update(['last_synced_at' => now()]);

        return $upserted;
    }

    private function syncShopify(ProductSource $source): int
    {
        if (! $source->api_url) {
            Log::warning('ProductSyncService: Shopify source missing api_url', ['source_id' => $source->id]);

            return 0;
        }

        $base = rtrim($source->api_url, '/');
        $sinceId = 0;
        $upserted = 0;

        do {
            $response = Http::withHeaders(['X-Shopify-Access-Token' => $source->api_key ?? ''])
                ->timeout(30)
                ->get("{$base}/products.json", [
                    'limit' => 250,
                    'since_id' => $sinceId,
                    'fields' => 'id,title,handle,variants,images,product_type,tags,status',
                ]);

            if ($response->failed()) {
                Log::error('ProductSyncService: Shopify API error', [
                    'source_id' => $source->id,
                    'status' => $response->status(),
                ]);
                break;
            }

            $products = $response->json('products', []);

            if (empty($products)) {
                break;
            }

            foreach ($products as $product) {
                $images = collect($product['images'] ?? [])
                    ->pluck('src')
                    ->filter(fn ($url) => str_starts_with((string) $url, 'https://'))
                    ->map(fn ($url) => strtok($url, '?')) // strip Shopify CDN params
                    ->values()
                    ->all();

                $price = collect($product['variants'] ?? [])->min('price');

                $websiteUrl = $source->website_url
                    ? rtrim($source->website_url, '/').'/products/'.($product['handle'] ?? '')
                    : null;

                ProductCache::updateOrCreate(
                    ['source_id' => $source->id, 'external_product_id' => (string) $product['id']],
                    [
                        'user_id' => $source->user_id,
                        'product_name' => $product['title'] ?? 'Unknown',
                        'sku' => null,
                        'category' => $product['product_type'] ?? null,
                        'price' => is_numeric($price) ? (float) $price : null,
                        'product_url' => $websiteUrl,
                        'image_urls' => $images,
                        'description' => null,
                        'stock_status' => ($product['status'] ?? 'active') === 'active' ? 'instock' : 'outofstock',
                        'keywords' => $product['tags'] ? explode(',', $product['tags']) : [],
                        'last_synced_at' => now(),
                    ]
                );

                $sinceId = (int) $product['id'];
                $upserted++;
            }
        } while (count($products) === 250);

        $source->update(['last_synced_at' => now()]);

        return $upserted;
    }

    private function syncCustomApi(ProductSource $source): int
    {
        if (! $source->api_url) {
            Log::warning('ProductSyncService: custom_api source missing api_url', ['source_id' => $source->id]);

            return 0;
        }

        $headers = [];
        if ($source->api_key) {
            $headers['Authorization'] = 'Bearer '.$source->api_key;
        }

        $response = Http::withHeaders($headers)
            ->timeout(30)
            ->get($source->api_url);

        if ($response->failed()) {
            Log::error('ProductSyncService: custom API error', [
                'source_id' => $source->id,
                'status' => $response->status(),
            ]);

            return 0;
        }

        $body = $response->json();

        // Support both a top-level array or `{"products": [...]}` wrapper
        $products = is_array($body) && isset($body[0])
            ? $body
            : ($body['products'] ?? $body['data'] ?? $body['items'] ?? []);

        $upserted = 0;

        foreach ($products as $product) {
            $images = [];

            if (isset($product['images'])) {
                $images = is_array($product['images']) ? $product['images'] : [$product['images']];
            } elseif (isset($product['image'])) {
                $images = [$product['image']];
            } elseif (isset($product['image_url'])) {
                $images = [$product['image_url']];
            }

            $images = collect($images)
                ->filter(fn ($url) => is_string($url) && str_starts_with($url, 'https://'))
                ->values()
                ->all();

            $externalId = (string) ($product['id'] ?? $product['sku'] ?? uniqid('', true));

            ProductCache::updateOrCreate(
                ['source_id' => $source->id, 'external_product_id' => $externalId],
                [
                    'user_id' => $source->user_id,
                    'product_name' => $product['name'] ?? $product['title'] ?? 'Unknown',
                    'sku' => $product['sku'] ?? null,
                    'category' => $product['category'] ?? null,
                    'price' => is_numeric($product['price'] ?? null) ? (float) $product['price'] : null,
                    'product_url' => $product['url'] ?? $product['product_url'] ?? $product['link'] ?? null,
                    'image_urls' => $images,
                    'description' => $product['description'] ?? $product['short_description'] ?? null,
                    'stock_status' => $product['stock_status'] ?? $product['availability'] ?? null,
                    'keywords' => is_array($product['tags'] ?? null) ? $product['tags'] : [],
                    'last_synced_at' => now(),
                ]
            );

            $upserted++;
        }

        $source->update(['last_synced_at' => now()]);

        return $upserted;
    }
}
