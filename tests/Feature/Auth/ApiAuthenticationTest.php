<?php

namespace Tests\Feature\Auth;

use App\Models\LoginLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_and_fetch_authenticated_api_profile(): void
    {
        $user = User::factory()->create();
        $role = Role::factory()->create(['slug' => 'client-admin']);
        $user->roles()->attach($role);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', $user->email)
            ->assertJsonStructure(['data' => ['token']]);

        $this->assertDatabaseHas(LoginLog::class, [
            'user_id' => $user->id,
        ]);
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'api',
        ]);

        $this->getJson('/api/auth/me', [
            'Authorization' => 'Bearer '.$response->json('data.token'),
        ])
            ->assertOk()
            ->assertJsonPath('data.roles.0', 'client-admin');
    }

    public function test_suspended_user_cannot_login_to_api(): void
    {
        $user = User::factory()->create(['status' => 'suspended']);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertUnprocessable();

        $this->assertGuest();
        $this->assertDatabaseMissing(LoginLog::class, [
            'user_id' => $user->id,
        ]);
    }

    public function test_authenticated_user_can_update_profile_and_change_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson('/api/auth/profile', [
                'name' => 'Updated Name',
                'email' => $user->email,
                'phone' => '+15555550100',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Name');

        $this->actingAs($user)
            ->putJson('/api/auth/change-password', [
                'current_password' => 'password',
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
            ])
            ->assertOk();
    }
}
