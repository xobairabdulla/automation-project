<?php

namespace Tests\Feature;

use App\Models\ProductSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSourceTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create(['status' => 'active']);
    }

    public function test_authenticated_user_can_view_product_sources_page(): void
    {
        $user = $this->user();
        $this->actingAs($user);

        $response = $this->get('/product-sources');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('client/product-sources'));
    }

    public function test_user_can_create_manual_product_source(): void
    {
        $user = $this->user();
        $this->actingAs($user);

        $this->post('/product-sources', [
            'name' => 'My Manual Store',
            'source_type' => 'manual',
            'is_active' => true,
            'product_image_reply_enabled' => true,
            'max_images_per_reply' => 4,
        ])->assertRedirect();

        $this->assertDatabaseHas('product_sources', [
            'user_id' => $user->id,
            'name' => 'My Manual Store',
            'source_type' => 'manual',
        ]);
    }

    public function test_user_cannot_delete_another_users_product_source(): void
    {
        $owner = $this->user();
        $attacker = $this->user();

        $source = ProductSource::factory()->create(['user_id' => $owner->id, 'source_type' => 'manual']);

        $this->actingAs($attacker);

        $this->delete("/product-sources/{$source->id}")->assertStatus(403);
    }

    public function test_user_can_delete_own_product_source(): void
    {
        $user = $this->user();
        $source = ProductSource::factory()->create(['user_id' => $user->id, 'source_type' => 'manual']);

        $this->actingAs($user);

        $this->delete("/product-sources/{$source->id}")->assertRedirect();

        $this->assertDatabaseMissing('product_sources', ['id' => $source->id]);
    }

    public function test_user_can_update_own_product_source(): void
    {
        $user = $this->user();
        $source = ProductSource::factory()->create(['user_id' => $user->id, 'source_type' => 'manual']);

        $this->actingAs($user);

        $this->put("/product-sources/{$source->id}", [
            'name' => 'Updated Name',
            'source_type' => 'manual',
            'is_active' => false,
            'product_image_reply_enabled' => true,
            'max_images_per_reply' => 3,
        ])->assertRedirect();

        $source->refresh();
        $this->assertEquals('Updated Name', $source->name);
        $this->assertFalse($source->is_active);
    }

    public function test_manual_source_accepts_product_with_https_image(): void
    {
        $user = $this->user();
        $source = ProductSource::factory()->create(['user_id' => $user->id, 'source_type' => 'manual']);

        $this->actingAs($user);

        $this->post("/product-sources/{$source->id}/products", [
            'product_name' => 'Black Panjabi',
            'price' => '850.00',
            'stock_status' => 'instock',
            'image_urls' => "https://example.com/panjabi.jpg\nhttps://example.com/panjabi2.jpg",
        ])->assertRedirect();

        $this->assertDatabaseHas('product_cache', [
            'source_id' => $source->id,
            'product_name' => 'Black Panjabi',
        ]);
    }

    public function test_manual_product_rejects_non_https_image_url(): void
    {
        $user = $this->user();
        $source = ProductSource::factory()->create(['user_id' => $user->id, 'source_type' => 'manual']);

        $this->actingAs($user);

        $response = $this->post("/product-sources/{$source->id}/products", [
            'product_name' => 'Test Product',
            'image_urls' => 'http://insecure.com/img.jpg',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('product_cache', ['product_name' => 'Test Product']);
    }
}
