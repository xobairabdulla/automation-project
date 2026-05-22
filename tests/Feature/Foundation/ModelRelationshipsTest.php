<?php

namespace Tests\Feature\Foundation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Tests\TestCase;

class ModelRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_belongs_to_tenant_and_roles_have_permissions(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $role = Role::factory()->create();
        $permission = Permission::factory()->create();

        $user->roles()->attach($role);
        $role->permissions()->attach($permission);

        $this->assertTrue($user->tenant->is($tenant));
        $this->assertTrue($user->roles->first()->is($role));
        $this->assertTrue($role->permissions->first()->is($permission));
    }
}
