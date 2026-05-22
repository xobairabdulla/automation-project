<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReplyTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'facebook_page_id',
        'name',
        'channel',
        'language',
        'body',
        'status',
    ];

    public function facebookPage(): BelongsTo
    {
        return $this->belongsTo(FacebookPage::class);
    }
}
