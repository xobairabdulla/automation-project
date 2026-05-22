<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\UsageLimit;
use App\Models\User;
use InvalidArgumentException;
use App\Notifications\UsageLimitExceededNotification;
use App\Notifications\UsageLimitWarningNotification;

class UsageLimitService
{
    public function canSendMessageReply(User $user): bool
    {
        $usageLimit = $this->currentUsageLimit($user);

        return $usageLimit->message_reply_used < $usageLimit->message_reply_limit;
    }

    public function canSendCommentReply(User $user): bool
    {
        $usageLimit = $this->currentUsageLimit($user);

        return $usageLimit->comment_reply_used < $usageLimit->comment_reply_limit;
    }

    public function canGenerateAIReply(User $user): bool
    {
        $usageLimit = $this->currentUsageLimit($user);

        return $usageLimit->ai_reply_used < $usageLimit->ai_reply_limit;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function incrementUsage(User $user, string $type, int $amount = 1, array $metadata = []): UsageLimit
    {
        if ($amount < 1) {
            throw new InvalidArgumentException('Usage amount must be at least 1.');
        }

        $usageLimit = $this->currentUsageLimit($user);
        $column = $this->usedColumnForType($type);

        $limitColumn = $column !== null ? $this->limitColumnForType($type) : null;

        if ($column !== null && $limitColumn !== null && ($usageLimit->{$column} + $amount) > $usageLimit->{$limitColumn}) {
            $user->usageLogs()->create([
                'tenant_id' => $user->tenant_id,
                'type' => 'failed_reply',
                'amount' => $amount,
                'metadata_json' => [
                    ...$metadata,
                    'blocked_type' => $type,
                    'reason' => 'limit_exceeded',
                ],
            ]);

            $user->notify(new UsageLimitExceededNotification($type));

            throw new InvalidArgumentException('Usage limit exceeded.');
        }

        if ($column !== null) {
            $usageLimit->increment($column, $amount);
            $usageLimit->refresh();

            $newUsed = $usageLimit->{$column};
            $limit = $usageLimit->{$limitColumn};
            if ($limit > 0 && $newUsed / $limit >= 0.8 && ($newUsed - $amount) / $limit < 0.8) {
                $user->notify(new UsageLimitWarningNotification($type, $newUsed, $limit));
            }
        }

        $user->usageLogs()->create([
            'tenant_id' => $user->tenant_id,
            'type' => $type,
            'amount' => $amount,
            'metadata_json' => $metadata,
        ]);

        return $usageLimit;
    }

    public function currentSubscription(User $user): Subscription
    {
        $subscription = $user->subscriptions()
            ->with('plan')
            ->whereIn('status', ['active', 'trial'])
            ->latest('starts_at')
            ->first();

        if ($subscription) {
            return $subscription;
        }

        $plan = Plan::query()
            ->where('status', 'active')
            ->where('name', 'Starter')
            ->first()
            ?? Plan::query()->where('status', 'active')->orderBy('monthly_price')->first()
            ?? Plan::factory()->create(['name' => 'Starter']);

        return $user->subscriptions()->create([
            'tenant_id' => $user->tenant_id,
            'plan_id' => $plan->id,
            'status' => 'trial',
            'billing_cycle' => 'monthly',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'next_billing_at' => now()->addMonth(),
        ])->load('plan');
    }

    public function currentUsageLimit(User $user): UsageLimit
    {
        $subscription = $this->currentSubscription($user);
        $usageLimit = $subscription->usageLimit()->first();

        if (! $usageLimit) {
            $usageLimit = $this->createUsageLimitFromSubscription($subscription);
        }

        if ($usageLimit->reset_at->isPast()) {
            $usageLimit->update([
                'message_reply_used' => 0,
                'comment_reply_used' => 0,
                'ai_reply_used' => 0,
                'reset_at' => now()->addMonth(),
            ]);
            $usageLimit->refresh();
        }

        return $usageLimit;
    }

    public function createUsageLimitForSubscription(Subscription $subscription): UsageLimit
    {
        return $this->createUsageLimitFromSubscription($subscription);
    }

    private function createUsageLimitFromSubscription(Subscription $subscription): UsageLimit
    {
        $plan = $subscription->plan;

        return $subscription->usageLimit()->create([
            'tenant_id' => $subscription->tenant_id,
            'user_id' => $subscription->user_id,
            'message_reply_limit' => $plan->message_reply_limit,
            'message_reply_used' => 0,
            'comment_reply_limit' => $plan->comment_reply_limit,
            'comment_reply_used' => 0,
            'ai_reply_limit' => $plan->ai_reply_limit,
            'ai_reply_used' => 0,
            'connected_page_limit' => $plan->connected_page_limit,
            'team_member_limit' => $plan->team_member_limit,
            'knowledge_base_limit' => $plan->knowledge_base_limit,
            'reset_at' => now()->addMonth(),
        ]);
    }

    private function usedColumnForType(string $type): ?string
    {
        return match ($type) {
            'message_reply' => 'message_reply_used',
            'comment_reply' => 'comment_reply_used',
            'ai_reply' => 'ai_reply_used',
            default => null,
        };
    }

    private function limitColumnForType(string $type): string
    {
        return match ($type) {
            'message_reply' => 'message_reply_limit',
            'comment_reply' => 'comment_reply_limit',
            'ai_reply' => 'ai_reply_limit',
            default => throw new InvalidArgumentException('Unsupported metered usage type.'),
        };
    }
}
