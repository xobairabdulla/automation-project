<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutomationRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'facebook_page_id',
        'name',
        'channel',
        'priority',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
        ];
    }

    public function facebookPage(): BelongsTo
    {
        return $this->belongsTo(FacebookPage::class);
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(AutomationRuleCondition::class);
    }

    public function actions(): HasMany
    {
        return $this->hasMany(AutomationRuleAction::class);
    }
}
