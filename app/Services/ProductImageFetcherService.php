<?php

namespace App\Services;

use App\Models\ProductCache;

class ProductImageFetcherService
{
    public function __construct(
        private readonly ProductSourceService $sourceService,
        private readonly ProductMatcherService $matcherService,
    ) {}

    /**
     * Fetch matching products with images for a given user and buyer message.
     *
     * @return array{products: ProductCache[], max_images: int}|null null if no active source
     */
    public function fetchForMessage(int $userId, string $message): ?array
    {
        $source = $this->sourceService->getActiveSourceForUser($userId);

        if (! $source) {
            return null;
        }

        $maxImages = $source->max_images_per_reply
            ?? (int) config('product_automation.default_max_images', 4);

        $products = $this->matcherService->matchProducts($userId, $message, $maxImages);

        // Broader fallback if precise keyword match returns nothing
        if (empty($products)) {
            $products = $this->matcherService->matchProductsRaw($userId, $message, $maxImages);
        }

        // Keep only products that actually have valid HTTPS images
        $products = array_values(
            array_filter($products, fn (ProductCache $p) => ! empty($p->validImageUrls()))
        );

        if (empty($products)) {
            return null;
        }

        return ['products' => $products, 'max_images' => $maxImages];
    }

    /**
     * Build a flat list of image URLs from matched products, respecting the per-reply limit.
     *
     * @param  ProductCache[]  $products
     * @return string[]
     */
    public function collectImageUrls(array $products, int $maxImages): array
    {
        $urls = [];

        foreach ($products as $product) {
            foreach ($product->validImageUrls() as $url) {
                $urls[] = $url;
                if (count($urls) >= $maxImages) {
                    return $urls;
                }
            }
        }

        return $urls;
    }

    /**
     * Build Generic Template elements for Facebook (max 10 cards per template).
     *
     * @param  ProductCache[]  $products
     * @return array<int, array<string, mixed>>
     */
    public function buildTemplateElements(array $products, int $maxCards = 4): array
    {
        $elements = [];

        foreach (array_slice($products, 0, $maxCards) as $product) {
            $images = $product->validImageUrls();
            if (empty($images)) {
                continue;
            }

            $element = [
                'title' => mb_substr($product->product_name, 0, 80),
                'image_url' => $images[0],
            ];

            if ($product->description) {
                $element['subtitle'] = mb_substr(strip_tags($product->description), 0, 80);
            }

            if ($product->product_url) {
                $element['default_action'] = [
                    'type' => 'web_url',
                    'url' => $product->product_url,
                    'webview_height_ratio' => 'FULL',
                ];

                $element['buttons'] = [[
                    'type' => 'web_url',
                    'url' => $product->product_url,
                    'title' => 'View Details',
                ]];
            }

            $elements[] = $element;
        }

        return $elements;
    }
}
