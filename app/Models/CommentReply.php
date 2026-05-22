<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommentReply extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'facebook_comment_id',
        'reply_text',
        'external_reply_id',
        'status',
        'error_message',
    ];

    public function facebookComment(): BelongsTo
    {
        return $this->belongsTo(FacebookComment::class);
    }
}
