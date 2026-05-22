<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LimitExtensionPackage extends Model
{
    /** @use HasFactory<\Database\Factories\LimitExtensionPackageFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'message_extra',
        'comment_extra',
        'ai_extra',
        'price',
        'currency',
        'stripe_price_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
