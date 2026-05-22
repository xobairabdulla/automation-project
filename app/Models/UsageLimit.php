<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsageLimit extends Model
{
    /** @use HasFactory<\Database\Factories\UsageLimitFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'subscription_id',
        'message_reply_limit',
        'message_reply_used',
        'comment_reply_limit',
        'comment_reply_used',
        'ai_reply_limit',
        'ai_reply_used',
        'connected_page_limit',
        'team_member_limit',
        'knowledge_base_limit',
        'reset_at',
    ];

    protected function casts(): array
    {
        return [
            'reset_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
