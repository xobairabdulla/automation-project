<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    /** @use HasFactory<\Database\Factories\PlanFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'monthly_price',
        'yearly_price',
        'stripe_monthly_price_id',
        'stripe_yearly_price_id',
        'message_reply_limit',
        'comment_reply_limit',
        'ai_reply_limit',
        'connected_page_limit',
        'team_member_limit',
        'knowledge_base_limit',
        'features_json',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'monthly_price' => 'decimal:2',
            'yearly_price' => 'decimal:2',
            'features_json' => 'array',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
