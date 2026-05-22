<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ConversationTag extends Model
{
    protected $fillable = ['tenant_id', 'name', 'color'];

    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_tag_pivot', 'conversation_tag_id', 'conversation_id');
    }
}
