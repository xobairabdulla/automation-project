<?php

namespace App\Models;

use Database\Factories\SystemSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class SystemSetting extends Model
{
    /** @use HasFactory<SystemSettingFactory> */
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

    /**
     * Get the real (decrypted) value for a setting key.
     */
    public static function getValue(string $key, ?string $default = null): ?string
    {
        $setting = static::where('key', $key)->first();

        if (! $setting) {
            return $default;
        }

        $raw = $setting->getRawOriginal('value_encrypted_or_json');

        if ($raw === null) {
            return $default;
        }

        if ($setting->is_sensitive) {
            try {
                return Crypt::decryptString($raw);
            } catch (\Throwable) {
                return $raw; // not yet encrypted (legacy plain value)
            }
        }

        return $raw;
    }

    /**
     * Upsert a setting. Sensitive values are stored encrypted.
     * Passing null or '' for a sensitive value is a no-op (keeps existing).
     */
    public static function setValue(string $key, ?string $value, string $type = 'string', bool $isSensitive = false): void
    {
        if ($isSensitive && ($value === null || $value === '')) {
            return;
        }

        $stored = ($isSensitive && $value !== null)
            ? Crypt::encryptString($value)
            : $value;

        static::updateOrCreate(
            ['key' => $key],
            ['value_encrypted_or_json' => $stored, 'type' => $type, 'is_sensitive' => $isSensitive]
        );
    }

    /**
     * Load all settings as a flat [key => decrypted_value] map for config override.
     *
     * @return array<string, string|null>
     */
    public static function allDecrypted(): array
    {
        $map = [];

        foreach (static::all() as $setting) {
            $raw = $setting->getRawOriginal('value_encrypted_or_json');

            if ($setting->is_sensitive && $raw) {
                try {
                    $map[$setting->key] = Crypt::decryptString($raw);
                } catch (\Throwable) {
                    $map[$setting->key] = $raw;
                }
            } else {
                $map[$setting->key] = $raw;
            }
        }

        return $map;
    }
}
