<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiPrompt extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'facebook_page_id',
        'preferred_language',
        'tone',
        'reply_length',
        'use_emoji',
        'unknown_answer_action',
        'restricted_instructions',
    ];

    protected function casts(): array
    {
        return [
            'use_emoji' => 'boolean',
        ];
    }

    public function facebookPage(): BelongsTo
    {
        return $this->belongsTo(FacebookPage::class);
    }
}
