<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Otp extends Model
{
    use HasFactory;

    protected $fillable = [
        'mobile',
        'otp',
        'expires_at',
        'last_sent_at',
        'attempts',
        'blocked_until',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_sent_at' => 'datetime',
            'blocked_until' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isBlocked(): bool
    {
        return $this->blocked_until !== null && $this->blocked_until->isFuture();
    }

    public function isValid(string $code): bool
    {
        return ! $this->isExpired() && ! $this->isBlocked() && $this->otp === $code;
    }
}
