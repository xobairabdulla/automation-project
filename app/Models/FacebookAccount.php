<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FacebookAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'facebook_user_id',
        'name',
        'access_token_encrypted',
        'token_expires_at',
    ];

    protected $hidden = ['access_token_encrypted'];

    protected function casts(): array
    {
        return [
            'access_token_encrypted' => 'encrypted',
            'token_expires_at' => 'datetime',
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

    public function pages(): HasMany
    {
        return $this->hasMany(FacebookPage::class);
    }

    public function isTokenExpired(): bool
    {
        return $this->token_expires_at !== null && $this->token_expires_at->isPast();
    }
}
