<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\UsageLimit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure plans exist
        $this->call(PlanSeeder::class);

        // Roles and permissions
        $permissions = collect([
            ['name' => 'Access Admin Dashboard', 'slug' => 'access-admin-dashboard'],
            ['name' => 'Manage Users', 'slug' => 'manage-users'],
            ['name' => 'Access Client Dashboard', 'slug' => 'access-client-dashboard'],
        ])->mapWithKeys(fn (array $p) => [
            $p['slug'] => Permission::firstOrCreate(['slug' => $p['slug']], ['name' => $p['name']]),
        ]);

        $superAdminRole = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);
        $superAdminRole->permissions()->syncWithoutDetaching($permissions->map->id->values()->all());

        $clientAdminRole = Role::firstOrCreate(['slug' => 'client-admin'], ['name' => 'Client Admin']);
        $clientAdminRole->permissions()->syncWithoutDetaching([
            $permissions['access-client-dashboard']->id,
        ]);

        // Demo super admin
        $superAdmin = User::firstOrCreate(['email' => 'admin@demo.com'], [
            'name' => 'Demo Admin',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $superAdmin->roles()->syncWithoutDetaching([$superAdminRole->id]);

        // Demo client owner
        $client = User::firstOrCreate(['email' => 'client@demo.com'], [
            'name' => 'Demo Client',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $client->roles()->syncWithoutDetaching([$clientAdminRole->id]);

        // Assign Growth plan subscription to demo client
        $growthPlan = Plan::where('name', 'Growth')->first();

        if ($growthPlan) {
            $subscription = Subscription::firstOrCreate(
                ['user_id' => $client->id, 'status' => 'active'],
                [
                    'tenant_id' => $client->tenant_id,
                    'plan_id' => $growthPlan->id,
                    'billing_cycle' => 'monthly',
                    'starts_at' => now(),
                    'next_billing_at' => now()->addMonth(),
                ]
            );

            UsageLimit::firstOrCreate(
                ['user_id' => $client->id],
                [
                    'tenant_id' => $client->tenant_id,
                    'subscription_id' => $subscription->id,
                    'message_reply_limit' => $growthPlan->message_reply_limit,
                    'message_reply_used' => 0,
                    'comment_reply_limit' => $growthPlan->comment_reply_limit,
                    'comment_reply_used' => 0,
                    'ai_reply_limit' => $growthPlan->ai_reply_limit,
                    'ai_reply_used' => 0,
                    'connected_page_limit' => $growthPlan->connected_page_limit,
                    'team_member_limit' => $growthPlan->team_member_limit,
                    'knowledge_base_limit' => $growthPlan->knowledge_base_limit,
                    'reset_at' => now()->addMonth(),
                ]
            );
        }

        // Note: AutomationRules require a real connected FacebookPage.
        // Connect a Facebook page via the UI first, then rules can be created.

        $this->command->info('Demo data seeded successfully.');
        $this->command->info('  Super Admin — admin@demo.com / password');
        $this->command->info('  Demo Client — client@demo.com / password');
    }
}
