<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiProviderSetting extends Model
{
    protected $fillable = [
        'provider_name',
        'api_key_encrypted',
        'model',
        'status',
    ];

    protected $hidden = ['api_key_encrypted'];

    protected function casts(): array
    {
        return [
            'api_key_encrypted' => 'encrypted',
        ];
    }

    public static function active(): ?self
    {
        return static::where('status', 'active')->first();
    }
}
