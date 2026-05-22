<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeBaseItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'knowledge_base_id',
        'title',
        'category',
        'content',
        'status',
    ];

    public function knowledgeBase(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBase::class);
    }
}
