<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductCache extends Model
{
    use HasFactory;

    protected $table = 'product_cache';

    protected $fillable = [
        'user_id',
        'source_id',
        'external_product_id',
        'product_name',
        'sku',
        'category',
        'price',
        'product_url',
        'image_urls',
        'description',
        'stock_status',
        'keywords',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'image_urls' => 'array',
            'keywords' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(ProductSource::class, 'source_id');
    }

    public function validImageUrls(): array
    {
        return collect($this->image_urls ?? [])
            ->filter(fn ($url) => str_starts_with((string) $url, 'https://'))
            ->values()
            ->all();
    }
}
