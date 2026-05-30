<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationProductContext extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'page_id',
        'facebook_user_id',
        'channel_type',
        'last_product_name',
        'last_product_url',
        'last_product_data',
        'last_sent_images',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'last_product_data' => 'array',
            'last_sent_images' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
