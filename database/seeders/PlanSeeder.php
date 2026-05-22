<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        collect([
            [
                'name' => 'Starter',
                'description' => 'For small teams starting Facebook automation.',
                'monthly_price' => 0,
                'yearly_price' => 0,
                'message_reply_limit' => 500,
                'comment_reply_limit' => 500,
                'ai_reply_limit' => 100,
                'connected_page_limit' => 1,
                'team_member_limit' => 2,
                'knowledge_base_limit' => 25,
                'features_json' => ['Messenger replies', 'Comment replies', 'Basic analytics'],
                'status' => 'active',
            ],
            [
                'name' => 'Growth',
                'description' => 'For growing businesses with higher automation volume.',
                'monthly_price' => 49,
                'yearly_price' => 490,
                'message_reply_limit' => 5000,
                'comment_reply_limit' => 5000,
                'ai_reply_limit' => 1000,
                'connected_page_limit' => 5,
                'team_member_limit' => 10,
                'knowledge_base_limit' => 250,
                'features_json' => ['Messenger replies', 'Comment replies', 'AI replies', 'Usage analytics'],
                'status' => 'active',
            ],
            [
                'name' => 'Pro',
                'description' => 'For agencies managing multiple pages and teams.',
                'monthly_price' => 149,
                'yearly_price' => 1490,
                'message_reply_limit' => 25000,
                'comment_reply_limit' => 25000,
                'ai_reply_limit' => 5000,
                'connected_page_limit' => 25,
                'team_member_limit' => 50,
                'knowledge_base_limit' => 1000,
                'features_json' => ['Messenger replies', 'Comment replies', 'AI replies', 'Advanced analytics', 'Team workflows'],
                'status' => 'active',
            ],
        ])->each(function (array $plan) {
            Plan::query()->updateOrCreate(
                ['name' => $plan['name']],
                $plan,
            );
        });
    }
}
