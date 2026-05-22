<?php

namespace App\Services;

use App\Models\AutomationRule;
use App\Models\AutomationRuleAction;
use App\Models\AutomationRuleCondition;
use App\Models\FacebookPage;

class RuleEngineService
{
    public function findMatchingAction(FacebookPage $page, string $channel, string $text): ?AutomationRuleAction
    {
        $rules = AutomationRule::with(['conditions', 'actions.replyTemplate'])
            ->where('facebook_page_id', $page->id)
            ->where('status', 'active')
            ->whereIn('channel', [$channel, 'both'])
            ->orderBy('priority')
            ->get();

        foreach ($rules as $rule) {
            if ($this->ruleMatches($rule->conditions->all(), $text)) {
                return $rule->actions->first();
            }
        }

        return null;
    }

    /**
     * @param  AutomationRuleCondition[]  $conditions
     */
    private function ruleMatches(array $conditions, string $text): bool
    {
        if (empty($conditions)) {
            return false;
        }

        foreach ($conditions as $condition) {
            if (! $this->conditionMatches($condition, $text)) {
                return false;
            }
        }

        return true;
    }

    private function conditionMatches(AutomationRuleCondition $condition, string $text): bool
    {
        $keywords = $condition->keywords_json ?? [];

        if (empty($keywords) && in_array($condition->condition_type, ['keyword_contains', 'exact_match', 'starts_with'])) {
            return false;
        }

        $subject = $condition->case_sensitive ? $text : mb_strtolower($text);

        foreach ($keywords as $keyword) {
            $needle = $condition->case_sensitive ? $keyword : mb_strtolower($keyword);

            $matched = match ($condition->condition_type) {
                'keyword_contains' => str_contains($subject, $needle),
                'exact_match' => $subject === $needle,
                'starts_with' => str_starts_with($subject, $needle),
                default => false,
            };

            if ($matched) {
                return true;
            }
        }

        return false;
    }
}
