<?php

namespace Tests\Feature;

use App\Models\ProductCache;
use App\Models\ProductSource;
use App\Models\User;
use App\Services\ProductMatcherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductMatcherTest extends TestCase
{
    use RefreshDatabase;

    private ProductMatcherService $matcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->matcher = new ProductMatcherService;
    }

    public function test_detects_bengali_image_intent(): void
    {
        $this->assertTrue($this->matcher->hasProductImageIntent('ছবি দেখতে চাই'));
        $this->assertTrue($this->matcher->hasProductImageIntent('picture den'));
        $this->assertTrue($this->matcher->hasProductImageIntent('ei product er photo den'));
        $this->assertTrue($this->matcher->hasProductImageIntent('available ache?'));
    }

    public function test_detects_english_image_intent(): void
    {
        $this->assertTrue($this->matcher->hasProductImageIntent('can I see a picture?'));
        $this->assertTrue($this->matcher->hasProductImageIntent('show me photos'));
        $this->assertTrue($this->matcher->hasProductImageIntent('send image please'));
    }

    public function test_no_false_positive_on_plain_greeting(): void
    {
        $this->assertFalse($this->matcher->hasProductImageIntent('hello'));
        $this->assertFalse($this->matcher->hasProductImageIntent('আমি একটি পণ্য কিনতে চাই'));
    }

    public function test_extracts_product_keywords(): void
    {
        $keywords = $this->matcher->extractProductKeywords('black panjabi er picture den');
        $this->assertContains('black', $keywords);
        $this->assertContains('panjabi', $keywords);
    }

    public function test_matches_products_in_cache(): void
    {
        $user = User::factory()->create();
        $source = ProductSource::factory()->create(['user_id' => $user->id, 'source_type' => 'manual']);

        ProductCache::create([
            'user_id' => $user->id,
            'source_id' => $source->id,
            'product_name' => 'Black Panjabi Cotton',
            'image_urls' => ['https://example.com/img.jpg'],
            'stock_status' => 'instock',
        ]);

        ProductCache::create([
            'user_id' => $user->id,
            'source_id' => $source->id,
            'product_name' => 'White Shirt',
            'image_urls' => ['https://example.com/shirt.jpg'],
            'stock_status' => 'instock',
        ]);

        $results = $this->matcher->matchProducts($user->id, 'black panjabi er picture den');

        $this->assertNotEmpty($results);
        $this->assertEquals('Black Panjabi Cotton', $results[0]->product_name);
    }

    public function test_price_intent_detection(): void
    {
        $this->assertTrue($this->matcher->hasPriceIntent('dam koto'));
        $this->assertTrue($this->matcher->hasPriceIntent('price koto?'));
        $this->assertTrue($this->matcher->hasPriceIntent('how much does it cost?'));
    }

    public function test_more_images_intent_detection(): void
    {
        $this->assertTrue($this->matcher->hasMoreImagesIntent('etar aro picture den'));
        $this->assertTrue($this->matcher->hasMoreImagesIntent('another photo please'));
        $this->assertTrue($this->matcher->hasMoreImagesIntent('more photos'));
    }
}
