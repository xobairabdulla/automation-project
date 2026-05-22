<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationRuleAction extends Model
{
    use HasFactory;

    protected $fillable = [
        'automation_rule_id',
        'action_type',
        'reply_text',
        'reply_template_id',
        'metadata_json',
    ];

    protected function casts(): array
    {
        return [
            'metadata_json' => 'array',
        ];
    }

    public function automationRule(): BelongsTo
    {
        return $this->belongsTo(AutomationRule::class);
    }

    public function replyTemplate(): BelongsTo
    {
        return $this->belongsTo(ReplyTemplate::class);
    }

    public function resolveReplyText(): ?string
    {
        if ($this->action_type === 'send_text') {
            return $this->reply_text;
        }

        if ($this->action_type === 'send_template' && $this->replyTemplate) {
            return $this->replyTemplate->body;
        }

        return null;
    }
}
