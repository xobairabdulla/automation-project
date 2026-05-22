<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiReplyLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'facebook_page_id',
        'conversation_id',
        'facebook_comment_id',
        'prompt',
        'response',
        'model',
        'tokens_used',
        'status',
        'error_message',
    ];

    public function facebookPage(): BelongsTo
    {
        return $this->belongsTo(FacebookPage::class);
    }
}
