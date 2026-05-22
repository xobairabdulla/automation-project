<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FacebookPost extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'facebook_page_id',
        'external_post_id',
        'message',
        'permalink_url',
        'created_time',
    ];

    protected function casts(): array
    {
        return [
            'created_time' => 'datetime',
        ];
    }

    public function facebookPage(): BelongsTo
    {
        return $this->belongsTo(FacebookPage::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(FacebookComment::class);
    }
}
