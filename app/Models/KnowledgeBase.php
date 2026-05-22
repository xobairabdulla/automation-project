<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeBase extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'facebook_page_id',
        'name',
        'status',
    ];

    public function facebookPage(): BelongsTo
    {
        return $this->belongsTo(FacebookPage::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(KnowledgeBaseItem::class);
    }
}
