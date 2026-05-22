<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FacebookComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'facebook_page_id',
        'facebook_post_id',
        'external_comment_id',
        'commenter_id',
        'commenter_name',
        'comment_text',
        'status',
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

    public function facebookPost(): BelongsTo
    {
        return $this->belongsTo(FacebookPost::class);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(CommentReply::class);
    }
}
