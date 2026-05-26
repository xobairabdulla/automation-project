<?php

namespace Database\Seeders;

use App\Models\AutomationRule;
use App\Models\AutomationRuleAction;
use App\Models\AutomationRuleCondition;
use App\Models\FacebookPage;
use Illuminate\Database\Seeder;

class AutomationSeeder extends Seeder
{
    /**
     * Default automation rules applied to every connected Facebook page.
     *
     * Each entry: [name, channel, priority, conditions[], action]
     *   conditions: [[type, keywords[], case_sensitive]]
     *   action: [type, reply_text]
     *
     * channel: 'message' | 'comment' | 'both'
     * condition types: 'keyword_contains' | 'exact_match' | 'starts_with'
     * action types: 'send_text' | 'do_nothing' | 'human_handover'
     *
     * @var array<int, array{name: string, channel: string, priority: int, conditions: array, action: array}>
     */
    private array $defaultRules = [
        [
            'name' => 'Greeting Reply',
            'channel' => 'both',
            'priority' => 10,
            'conditions' => [
                [
                    'type' => 'keyword_contains',
                    'keywords' => ['hi', 'hello', 'hey', 'salam', 'salaam', 'assalamu', 'hola'],
                    'case_sensitive' => false,
                ],
            ],
            'action' => [
                'type' => 'send_text',
                'reply_text' => 'Hello! Welcome. How can we help you today? 😊',
            ],
        ],
        [
            'name' => 'Help / Support Request',
            'channel' => 'both',
            'priority' => 20,
            'conditions' => [
                [
                    'type' => 'keyword_contains',
                    'keywords' => ['help', 'support', 'problem', 'issue', 'error', 'not working'],
                    'case_sensitive' => false,
                ],
            ],
            'action' => [
                'type' => 'send_text',
                'reply_text' => "We're here to help! Please describe your issue and our team will respond as soon as possible.",
            ],
        ],
        [
            'name' => 'Price / Cost Inquiry',
            'channel' => 'both',
            'priority' => 30,
            'conditions' => [
                [
                    'type' => 'keyword_contains',
                    'keywords' => ['price', 'cost', 'how much', 'rate', 'dam koto', 'daam', 'মূল্য', 'দাম'],
                    'case_sensitive' => false,
                ],
            ],
            'action' => [
                'type' => 'send_text',
                'reply_text' => 'Thank you for your interest! Please inbox us for pricing details or visit our website.',
            ],
        ],
        [
            'name' => 'Thank You Reply',
            'channel' => 'both',
            'priority' => 40,
            'conditions' => [
                [
                    'type' => 'keyword_contains',
                    'keywords' => ['thanks', 'thank you', 'dhonnobad', 'ধন্যবাদ'],
                    'case_sensitive' => false,
                ],
            ],
            'action' => [
                'type' => 'send_text',
                'reply_text' => "You're welcome! Feel free to reach out anytime. 🙏",
            ],
        ],
    ];

    public function run(): void
    {
        $pages = FacebookPage::all();

        if ($pages->isEmpty()) {
            $this->command->warn('No Facebook pages found. Connect a page first, then re-run this seeder.');

            return;
        }

        foreach ($pages as $page) {
            $this->command->info("Processing page: {$page->page_name} (ID: {$page->page_id})");

            $page->update([
                'message_automation_enabled' => true,
                'comment_automation_enabled' => true,
            ]);

            foreach ($this->defaultRules as $ruleDef) {
                $existingRule = AutomationRule::where('facebook_page_id', $page->id)
                    ->where('name', $ruleDef['name'])
                    ->first();

                if ($existingRule) {
                    $this->command->line("  Skipping (already exists): {$ruleDef['name']}");

                    continue;
                }

                $rule = AutomationRule::create([
                    'tenant_id' => $page->tenant_id ?? $page->user_id,
                    'facebook_page_id' => $page->id,
                    'name' => $ruleDef['name'],
                    'channel' => $ruleDef['channel'],
                    'priority' => $ruleDef['priority'],
                    'status' => 'active',
                ]);

                foreach ($ruleDef['conditions'] as $condDef) {
                    AutomationRuleCondition::create([
                        'automation_rule_id' => $rule->id,
                        'condition_type' => $condDef['type'],
                        'keywords_json' => $condDef['keywords'],
                        'case_sensitive' => $condDef['case_sensitive'],
                    ]);
                }

                AutomationRuleAction::create([
                    'automation_rule_id' => $rule->id,
                    'action_type' => $ruleDef['action']['type'],
                    'reply_text' => $ruleDef['action']['reply_text'],
                ]);

                $this->command->line("  Created rule: {$ruleDef['name']}");
            }
        }

        $this->command->info('Automation seeding complete.');
    }
}
