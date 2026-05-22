<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationRuleCondition extends Model
{
    use HasFactory;

    protected $fillable = [
        'automation_rule_id',
        'condition_type',
        'keywords_json',
        'case_sensitive',
    ];

    protected function casts(): array
    {
        return [
            'keywords_json' => 'array',
            'case_sensitive' => 'boolean',
        ];
    }

    public function automationRule(): BelongsTo
    {
        return $this->belongsTo(AutomationRule::class);
    }
}
