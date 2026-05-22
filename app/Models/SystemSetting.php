<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    /** @use HasFactory<\Database\Factories\SystemSettingFactory> */
    use HasFactory;

    protected $fillable = [
        'key',
        'value_encrypted_or_json',
        'type',
        'is_sensitive',
    ];

    protected function casts(): array
    {
        return [
            'is_sensitive' => 'boolean',
        ];
    }

    public function getValueEncryptedOrJsonAttribute(?string $value): ?string
    {
        if ($this->is_sensitive && $value !== null) {
            return '••••••••';
        }

        return $value;
    }
}
