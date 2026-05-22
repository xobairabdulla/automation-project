<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(PlanSeeder::class);

        $permissions = collect([
            ['name' => 'Access Admin Dashboard', 'slug' => 'access-admin-dashboard'],
            ['name' => 'Manage Users', 'slug' => 'manage-users'],
            ['name' => 'Access Client Dashboard', 'slug' => 'access-client-dashboard'],
        ])->mapWithKeys(function (array $permission) {
            return [
                $permission['slug'] => Permission::query()->firstOrCreate(
                    ['slug' => $permission['slug']],
                    ['name' => $permission['name']],
                ),
            ];
        });

        $roles = collect([
            'super-admin' => ['name' => 'Super Admin', 'permissions' => $permissions->keys()->all()],
            'client-admin' => ['name' => 'Client Admin', 'permissions' => ['access-client-dashboard']],
            'agent' => ['name' => 'Agent', 'permissions' => ['access-client-dashboard']],
            'support-admin' => ['name' => 'Support Admin', 'permissions' => ['access-client-dashboard']],
        ])->mapWithKeys(function (array $role, string $slug) use ($permissions) {
            $model = Role::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => $role['name']],
            );

            $model->permissions()->sync(
                collect($role['permissions'])->map(fn (string $permission) => $permissions[$permission]->id)->all()
            );

            return [$slug => $model];
        });

        $user = User::query()->firstOrCreate([
            'email' => 'test@example.com',
        ], [
            'name' => 'Test User',
            'password' => 'password',
            'status' => 'active',
        ]);

        $user->roles()->syncWithoutDetaching([$roles['super-admin']->id]);

        $this->call(DemoSeeder::class);
    }
}
