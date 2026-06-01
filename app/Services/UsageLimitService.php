<?php

namespace App\Services;

use App\Models\LimitExtensionPackage;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\UsageLimit;
use App\Models\User;
use App\Notifications\UsageLimitExceededNotification;
use App\Notifications\UsageLimitWarningNotification;
use InvalidArgumentException;

class UsageLimitService
{
    public function canSendMessageReply(User $user): bool
    {
        $limit = $this->currentUsageLimit($user);

        return $limit->message_reply_limit === 0 || $limit->message_reply_used < $limit->message_reply_limit;
    }

    public function canSendCommentReply(User $user): bool
    {
        $limit = $this->currentUsageLimit($user);

        return $limit->comment_reply_limit === 0 || $limit->comment_reply_used < $limit->comment_reply_limit;
    }

    public function canGenerateAIReply(User $user): bool
    {
        $limit = $this->currentUsageLimit($user);

        return $limit->ai_reply_limit === 0 || $limit->ai_reply_used < $limit->ai_reply_limit;
    }

    public function canSendImageReply(User $user): bool
    {
        $limit = $this->currentUsageLimit($user);

        return $limit->image_send_limit === 0 || $limit->image_send_used < $limit->image_send_limit;
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

        if ($column !== null && $limitColumn !== null) {
            $currentLimit = $usageLimit->{$limitColumn};

            // 0 means unlimited (legacy plan-based free tier) — skip check
            if ($currentLimit > 0 && ($usageLimit->{$column} + $amount) > $currentLimit) {
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
        }

        if ($column !== null) {
            $usageLimit->increment($column, $amount);
            $usageLimit->refresh();

            if ($limitColumn !== null) {
                $newUsed = $usageLimit->{$column};
                $limit = $usageLimit->{$limitColumn};

                if ($limit > 0 && $newUsed / $limit >= 0.8 && ($newUsed - $amount) / $limit < 0.8) {
                    $user->notify(new UsageLimitWarningNotification($type, $newUsed, $limit));
                }
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

    /**
     * Fulfil a package purchase: add credits to the user's UsageLimit.
     */
    public function fulfillPackagePurchase(User $user, LimitExtensionPackage $package): void
    {
        $usageLimit = $this->currentUsageLimit($user);

        if ($package->message_extra > 0) {
            $usageLimit->increment('message_reply_limit', $package->message_extra);
        }

        if ($package->comment_extra > 0) {
            $usageLimit->increment('comment_reply_limit', $package->comment_extra);
        }

        if ($package->ai_extra > 0) {
            $usageLimit->increment('ai_reply_limit', $package->ai_extra);
        }

        if ($package->image_send_extra > 0) {
            $usageLimit->increment('image_send_limit', $package->image_send_extra);
        }
    }

    /**
     * Returns the user's active UsageLimit, creating one if it doesn't exist.
     * The new package-based model does NOT require a Subscription.
     */
    public function currentUsageLimit(User $user): UsageLimit
    {
        $tenantId = $user->effectiveTenantId();

        $usageLimit = UsageLimit::where('user_id', $tenantId)->latest('id')->first();

        if (! $usageLimit) {
            $usageLimit = UsageLimit::create([
                'tenant_id' => null,
                'user_id' => $tenantId,
                'subscription_id' => null,
                'message_reply_limit' => 0,
                'message_reply_used' => 0,
                'comment_reply_limit' => 0,
                'comment_reply_used' => 0,
                'ai_reply_limit' => 0,
                'ai_reply_used' => 0,
                'image_send_limit' => 0,
                'image_send_used' => 0,
                'connected_page_limit' => 3,
                'team_member_limit' => 1,
                'knowledge_base_limit' => 1,
                'reset_at' => now()->addYears(10),
            ]);
        }

        return $usageLimit;
    }

    /**
     * @deprecated Use currentUsageLimit() directly — kept for backward compatibility
     */
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
            'ends_at' => now()->addYears(10),
            'next_billing_at' => now()->addYears(10),
        ])->load('plan');
    }

    public function createUsageLimitForSubscription(Subscription $subscription): UsageLimit
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
            'image_send_limit' => 0,
            'image_send_used' => 0,
            'connected_page_limit' => $plan->connected_page_limit,
            'team_member_limit' => $plan->team_member_limit,
            'knowledge_base_limit' => $plan->knowledge_base_limit,
            'reset_at' => now()->addYears(10),
        ]);
    }

    private function usedColumnForType(string $type): ?string
    {
        return match ($type) {
            'message_reply' => 'message_reply_used',
            'comment_reply' => 'comment_reply_used',
            'ai_reply' => 'ai_reply_used',
            'image_send' => 'image_send_used',
            default => null,
        };
    }

    private function limitColumnForType(string $type): string
    {
        return match ($type) {
            'message_reply' => 'message_reply_limit',
            'comment_reply' => 'comment_reply_limit',
            'ai_reply' => 'ai_reply_limit',
            'image_send' => 'image_send_limit',
            default => throw new InvalidArgumentException('Unsupported metered usage type.'),
        };
    }
}
