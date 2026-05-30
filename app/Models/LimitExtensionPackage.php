<?php

namespace App\Models;

use Database\Factories\LimitExtensionPackageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LimitExtensionPackage extends Model
{
    /** @use HasFactory<LimitExtensionPackageFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'message_extra',
        'comment_extra',
        'ai_extra',
        'image_send_extra',
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
