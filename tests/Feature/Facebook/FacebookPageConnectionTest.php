<?php

namespace Tests\Feature\Facebook;

use App\Models\FacebookAccount;
use App\Models\FacebookPage;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FacebookPageConnectionTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $role = Role::firstOrCreate(['slug' => 'client-admin'], ['name' => 'Client Admin']);
        $user->roles()->attach($role);

        return $user;
    }

    private function makePage(User $user, array $attributes = []): FacebookPage
    {
        $account = FacebookAccount::create([
            'user_id' => $user->id,
            'facebook_user_id' => 'fb_user_'.uniqid(),
            'name' => 'Test FB User',
            'access_token_encrypted' => 'fake_user_token',
        ]);

        return FacebookPage::create(array_merge([
            'user_id' => $user->id,
            'facebook_account_id' => $account->id,
            'page_id' => 'page_'.uniqid(),
            'page_name' => 'Test Page',
            'page_access_token_encrypted' => 'fake_page_token',
            'is_connected' => false,
            'automation_enabled' => false,
            'message_automation_enabled' => false,
            'comment_automation_enabled' => false,
        ], $attributes));
    }

    public function test_api_returns_connect_url(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        $this->getJson('/api/facebook/connect-url')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['url']]);
    }

    public function test_api_lists_user_pages(): void
    {
        $user = $this->makeUser();
        $this->makePage($user);
        Sanctum::actingAs($user);

        $this->getJson('/api/facebook/pages')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_user_cannot_see_other_users_pages(): void
    {
        $user = $this->makeUser();
        $other = $this->makeUser();
        $this->makePage($other);
        Sanctum::actingAs($user);

        $this->getJson('/api/facebook/pages')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_user_can_connect_their_page(): void
    {
        $user = $this->makeUser();
        $page = $this->makePage($user);
        Sanctum::actingAs($user);

        $this->postJson("/api/facebook/pages/{$page->id}/connect")
            ->assertOk();

        $this->assertDatabaseHas('facebook_pages', ['id' => $page->id, 'is_connected' => true]);
    }

    public function test_user_cannot_connect_another_users_page(): void
    {
        $user = $this->makeUser();
        $other = $this->makeUser();
        $page = $this->makePage($other);
        Sanctum::actingAs($user);

        $this->postJson("/api/facebook/pages/{$page->id}/connect")
            ->assertForbidden();
    }

    public function test_user_can_disconnect_page_and_automation_is_disabled(): void
    {
        $user = $this->makeUser();
        $page = $this->makePage($user, [
            'is_connected' => true,
            'automation_enabled' => true,
            'message_automation_enabled' => true,
        ]);
        Sanctum::actingAs($user);

        $this->postJson("/api/facebook/pages/{$page->id}/disconnect")
            ->assertOk();

        $this->assertDatabaseHas('facebook_pages', [
            'id' => $page->id,
            'is_connected' => false,
            'automation_enabled' => false,
            'message_automation_enabled' => false,
        ]);
    }

    public function test_user_can_enable_automation(): void
    {
        $user = $this->makeUser();
        $page = $this->makePage($user, ['is_connected' => true]);
        Sanctum::actingAs($user);

        $this->postJson("/api/facebook/pages/{$page->id}/enable-automation")
            ->assertOk();

        $this->assertDatabaseHas('facebook_pages', ['id' => $page->id, 'automation_enabled' => true]);
    }

    public function test_user_can_disable_automation(): void
    {
        $user = $this->makeUser();
        $page = $this->makePage($user, ['is_connected' => true, 'automation_enabled' => true]);
        Sanctum::actingAs($user);

        $this->postJson("/api/facebook/pages/{$page->id}/disable-automation")
            ->assertOk();

        $this->assertDatabaseHas('facebook_pages', ['id' => $page->id, 'automation_enabled' => false]);
    }

    public function test_page_tokens_are_stored_encrypted(): void
    {
        $user = $this->makeUser();
        $page = $this->makePage($user);

        $rawRow = \Illuminate\Support\Facades\DB::table('facebook_pages')->where('id', $page->id)->first();

        $this->assertNotEquals('fake_page_token', $rawRow->page_access_token_encrypted);
        $this->assertEquals('fake_page_token', $page->fresh()->page_access_token_encrypted);
    }

    public function test_dashboard_connected_pages_count_reflects_real_pages(): void
    {
        $user = $this->makeUser();
        $this->makePage($user, ['is_connected' => true]);
        $this->makePage($user, ['is_connected' => true]);
        $this->makePage($user, ['is_connected' => false]);
        Sanctum::actingAs($user);

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.stats.connected_pages', 2);
    }
}
