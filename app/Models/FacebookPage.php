<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FacebookPage extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'facebook_account_id',
        'page_id',
        'page_name',
        'page_access_token_encrypted',
        'token_expires_at',
        'is_connected',
        'automation_enabled',
        'message_automation_enabled',
        'comment_automation_enabled',
        'last_webhook_received_at',
    ];

    protected $hidden = ['page_access_token_encrypted'];

    protected function casts(): array
    {
        return [
            'page_access_token_encrypted' => 'encrypted',
            'token_expires_at' => 'datetime',
            'is_connected' => 'boolean',
            'automation_enabled' => 'boolean',
            'message_automation_enabled' => 'boolean',
            'comment_automation_enabled' => 'boolean',
            'last_webhook_received_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function facebookAccount(): BelongsTo
    {
        return $this->belongsTo(FacebookAccount::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(FacebookPost::class);
    }

    public function facebookComments(): HasMany
    {
        return $this->hasMany(FacebookComment::class);
    }

    public function isTokenExpired(): bool
    {
        return $this->token_expires_at !== null && $this->token_expires_at->isPast();
    }
}
