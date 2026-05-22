<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyUsageStat extends Model
{
    /** @use HasFactory<\Database\Factories\DailyUsageStatFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'date',
        'messages_received',
        'comments_received',
        'message_replies_sent',
        'comment_replies_sent',
        'ai_replies_sent',
        'manual_replies_sent',
        'human_handovers',
        'failed_replies',
    ];

}
