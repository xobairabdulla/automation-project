<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'facebook_page_id',
        'external_customer_id',
        'name',
        'profile_picture_url',
        'phone',
        'email',
        'tags_json',
        'facebook_first_name',
        'facebook_last_name',
        'facebook_locale',
        'facebook_timezone',
        'profile_synced_at',
    ];

    protected $appends = ['display_name', 'avatar_url'];

    protected function casts(): array
    {
        return [
            'tags_json' => 'array',
            'profile_synced_at' => 'datetime',
        ];
    }

    protected function displayName(): Attribute
    {
        return Attribute::make(
            get: fn () => filled($this->name) ? $this->name : 'Unknown Customer',
        );
    }

    protected function avatarUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->profile_picture_url ?: null,
        );
    }

    public function profileSyncedRecently(): bool
    {
        return $this->profile_synced_at !== null && $this->profile_synced_at->gt(now()->subHours(24));
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function facebookPage(): BelongsTo
    {
        return $this->belongsTo(FacebookPage::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}
