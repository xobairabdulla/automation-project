<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductSource extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'source_type',
        'name',
        'website_url',
        'api_url',
        'api_key',
        'api_secret',
        'config',
        'is_active',
        'product_image_reply_enabled',
        'max_images_per_reply',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'api_secret' => 'encrypted',
            'config' => 'array',
            'is_active' => 'boolean',
            'product_image_reply_enabled' => 'boolean',
            'max_images_per_reply' => 'integer',
            'last_synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(ProductCache::class, 'source_id');
    }
}
