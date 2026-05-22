<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'facebook_page_id',
        'customer_id',
        'channel',
        'status',
        'assigned_to',
        'human_takeover',
        'human_takeover_reason',
        'human_takeover_by',
        'human_takeover_at',
        'is_read',
        'last_customer_message_at',
        'last_reply_at',
    ];

    protected function casts(): array
    {
        return [
            'human_takeover' => 'boolean',
            'is_read' => 'boolean',
            'human_takeover_at' => 'datetime',
            'last_customer_message_at' => 'datetime',
            'last_reply_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function facebookPage(): BelongsTo
    {
        return $this->belongsTo(FacebookPage::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ConversationAssignment::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(InternalNote::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(ConversationTag::class, 'conversation_tag_pivot', 'conversation_id', 'conversation_tag_id');
    }
}
