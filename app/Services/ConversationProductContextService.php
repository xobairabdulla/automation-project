<?php

namespace App\Services;

use App\Models\ConversationProductContext;
use App\Models\ProductCache;

class ConversationProductContextService
{
    public function getContext(int $userId, string $pageId, string $facebookUserId): ?ConversationProductContext
    {
        $context = ConversationProductContext::where('user_id', $userId)
            ->where('page_id', $pageId)
            ->where('facebook_user_id', $facebookUserId)
            ->first();

        if ($context && $context->isExpired()) {
            $context->delete();

            return null;
        }

        return $context;
    }

    public function saveContext(
        int $userId,
        string $pageId,
        string $facebookUserId,
        string $channelType,
        ProductCache $product,
        array $sentImages = [],
    ): ConversationProductContext {
        $ttlHours = (int) config('product_automation.context_ttl_hours', 24);

        return ConversationProductContext::updateOrCreate(
            [
                'user_id' => $userId,
                'page_id' => $pageId,
                'facebook_user_id' => $facebookUserId,
            ],
            [
                'channel_type' => $channelType,
                'last_product_name' => $product->product_name,
                'last_product_url' => $product->product_url,
                'last_product_data' => [
                    'id' => $product->id,
                    'name' => $product->product_name,
                    'price' => $product->price,
                    'url' => $product->product_url,
                    'description' => $product->description,
                    'stock_status' => $product->stock_status,
                ],
                'last_sent_images' => $sentImages,
                'expires_at' => now()->addHours($ttlHours),
            ]
        );
    }

    public function clearContext(int $userId, string $pageId, string $facebookUserId): void
    {
        ConversationProductContext::where('user_id', $userId)
            ->where('page_id', $pageId)
            ->where('facebook_user_id', $facebookUserId)
            ->delete();
    }
}
