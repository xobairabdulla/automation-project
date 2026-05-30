<?php

namespace App\Services;

use App\Models\ProductCache;

class ProductMatcherService
{
    public function hasProductImageIntent(string $message): bool
    {
        $lower = mb_strtolower($message);
        $keywords = config('product_automation.image_intent_keywords', []);

        foreach ($keywords as $keyword) {
            if (str_contains($lower, mb_strtolower($keyword))) {
                return true;
            }
        }

        return false;
    }

    public function hasPriceIntent(string $message): bool
    {
        $lower = mb_strtolower($message);

        foreach (config('product_automation.price_intent_keywords', []) as $keyword) {
            if (str_contains($lower, mb_strtolower($keyword))) {
                return true;
            }
        }

        return false;
    }

    public function hasMoreImagesIntent(string $message): bool
    {
        $lower = mb_strtolower($message);

        foreach (config('product_automation.more_images_keywords', []) as $keyword) {
            if (str_contains($lower, mb_strtolower($keyword))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract product search keywords from a buyer message.
     *
     * @return string[]
     */
    public function extractProductKeywords(string $message): array
    {
        $lower = mb_strtolower($message);

        // Strip intent/filler keywords to isolate product terms
        $stripWords = array_merge(
            config('product_automation.image_intent_keywords', []),
            config('product_automation.strip_words', []),
        );

        foreach ($stripWords as $word) {
            $lower = str_replace(mb_strtolower($word), ' ', $lower);
        }

        $lower = preg_replace('/[?!।,।\.]+/', ' ', $lower) ?? $lower;
        $lower = preg_replace('/\s+/', ' ', $lower) ?? $lower;
        $lower = trim($lower);

        if ($lower === '') {
            return [];
        }

        // Split on whitespace and return tokens ≥ 2 chars
        return array_values(
            array_filter(
                explode(' ', $lower),
                fn ($t) => mb_strlen($t) >= 2,
            )
        );
    }

    /**
     * Search product_cache for products matching the extracted keywords.
     *
     * @return ProductCache[]
     */
    public function matchProducts(int $userId, string $message, int $limit = 5): array
    {
        $keywords = $this->extractProductKeywords($message);

        if (empty($keywords)) {
            return [];
        }

        $query = ProductCache::where('user_id', $userId);

        // Build OR conditions for each keyword across multiple columns
        $query->where(function ($q) use ($keywords) {
            foreach ($keywords as $keyword) {
                $q->orWhere('product_name', 'like', "%{$keyword}%")
                    ->orWhere('category', 'like', "%{$keyword}%")
                    ->orWhere('sku', 'like', "%{$keyword}%")
                    ->orWhereRaw('LOWER(keywords) LIKE ?', ["%{$keyword}%"]);
            }
        });

        return $query->limit($limit)->get()->all();
    }

    /**
     * Broader fallback: search using the full raw message.
     *
     * @return ProductCache[]
     */
    public function matchProductsRaw(int $userId, string $rawMessage, int $limit = 5): array
    {
        $lower = mb_strtolower($rawMessage);

        return ProductCache::where('user_id', $userId)
            ->where(function ($q) use ($lower) {
                $q->where('product_name', 'like', "%{$lower}%")
                    ->orWhere('category', 'like', "%{$lower}%");
            })
            ->limit($limit)
            ->get()
            ->all();
    }
}
