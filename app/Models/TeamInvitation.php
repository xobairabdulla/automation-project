<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamInvitation extends Model
{
    /** @use HasFactory<\Database\Factories\TeamInvitationFactory> */
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'email',
        'role',
        'token',
        'accepted_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null && ! $this->isExpired();
    }
}
