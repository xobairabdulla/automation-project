<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsageLog extends Model
{
    /** @use HasFactory<\Database\Factories\UsageLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'type',
        'amount',
        'source_id',
        'source_type',
        'metadata_json',
    ];

    protected function casts(): array
    {
        return [
            'metadata_json' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
