<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    private function superAdminUser(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);
        $user->roles()->attach($role);

        return $user;
    }

    private function regularUser(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['slug' => 'client-admin'], ['name' => 'Client Admin']);
        $user->roles()->attach($role);

        return $user;
    }

    public function test_admin_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/admin/login');

        $response->assertStatus(200);
    }

    public function test_authenticated_super_admin_visiting_login_is_redirected_to_dashboard(): void
    {
        $user = $this->superAdminUser();

        $response = $this->actingAs($user)->get('/admin/login');

        $response->assertRedirect(route('admin.dashboard'));
    }

    public function test_super_admin_can_login_via_admin_login(): void
    {
        $user = $this->superAdminUser();

        $response = $this->post('/admin/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('admin.dashboard'));
    }

    public function test_regular_user_cannot_login_via_admin_login(): void
    {
        $user = $this->regularUser();

        $response = $this->post('/admin/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_admin_logout_redirects_to_admin_login(): void
    {
        $user = $this->superAdminUser();

        $response = $this->actingAs($user)->post('/admin/logout');

        $this->assertGuest();
        $response->assertRedirect(route('admin.login'));
    }

    public function test_super_admin_login_via_normal_login_redirects_to_otp_step(): void
    {
        Mail::fake();

        $user = $this->superAdminUser();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        // Credentials valid → OTP sent → redirect to OTP verify page
        $this->assertGuest();
        $response->assertRedirect(route('login.otp'));
    }

    public function test_wrong_credentials_cannot_access_admin_login(): void
    {
        $response = $this->post('/admin/login', [
            'email' => 'nobody@example.com',
            'password' => 'wrongpassword',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }
}
