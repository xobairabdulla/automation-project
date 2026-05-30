<?php

namespace Tests\Feature;

use App\Models\ConversationProductContext;
use App\Models\ProductCache;
use App\Models\ProductSource;
use App\Models\User;
use App\Services\ConversationProductContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductIntentFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_conversation_context_saves_and_retrieves(): void
    {
        $user = User::factory()->create();
        $source = ProductSource::factory()->create(['user_id' => $user->id, 'source_type' => 'manual']);

        $product = ProductCache::create([
            'user_id' => $user->id,
            'source_id' => $source->id,
            'product_name' => 'Black Panjabi',
            'image_urls' => ['https://example.com/img.jpg'],
        ]);

        $service = new ConversationProductContextService;

        $service->saveContext(
            userId: $user->id,
            pageId: '123456789',
            facebookUserId: 'psid_abc',
            channelType: 'message',
            product: $product,
            sentImages: ['https://example.com/img.jpg'],
        );

        $context = $service->getContext($user->id, '123456789', 'psid_abc');

        $this->assertNotNull($context);
        $this->assertEquals('Black Panjabi', $context->last_product_name);
        $this->assertEquals(['https://example.com/img.jpg'], $context->last_sent_images);
    }

    public function test_expired_context_returns_null(): void
    {
        $user = User::factory()->create();

        ConversationProductContext::create([
            'user_id' => $user->id,
            'page_id' => '111',
            'facebook_user_id' => 'psid_expired',
            'channel_type' => 'message',
            'expires_at' => now()->subHour(),
        ]);

        $service = new ConversationProductContextService;
        $context = $service->getContext($user->id, '111', 'psid_expired');

        $this->assertNull($context);
        $this->assertDatabaseMissing('conversation_product_contexts', [
            'facebook_user_id' => 'psid_expired',
        ]);
    }

    public function test_product_cache_valid_image_urls_filters_http(): void
    {
        $user = User::factory()->create();
        $source = ProductSource::factory()->create(['user_id' => $user->id, 'source_type' => 'manual']);

        $product = ProductCache::create([
            'user_id' => $user->id,
            'source_id' => $source->id,
            'product_name' => 'Test',
            'image_urls' => [
                'https://secure.example.com/img.jpg',
                'http://insecure.example.com/img.jpg',
                'https://secure2.example.com/img2.jpg',
            ],
        ]);

        $validUrls = $product->validImageUrls();

        $this->assertCount(2, $validUrls);
        $this->assertNotContains('http://insecure.example.com/img.jpg', $validUrls);
    }
}
