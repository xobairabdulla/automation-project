<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $this->actingAs($user = User::factory()->create());
        $user->roles()->attach(Role::factory()->create(['slug' => 'client-admin']));

        $this->get('/dashboard')->assertOk();
    }

    public function test_admin_dashboard_requires_super_admin_role(): void
    {
        $clientAdmin = User::factory()->create();
        $clientAdmin->roles()->attach(Role::factory()->create(['slug' => 'client-admin']));

        $this->actingAs($clientAdmin)->get('/admin/dashboard')->assertForbidden();

        $superAdmin = User::factory()->create();
        $superAdmin->roles()->attach(Role::factory()->create(['slug' => 'super-admin']));

        $this->actingAs($superAdmin)->get('/admin/dashboard')->assertOk();
    }
}
