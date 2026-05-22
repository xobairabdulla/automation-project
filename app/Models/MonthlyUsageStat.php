<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MonthlyUsageStat extends Model
{
    /** @use HasFactory<\Database\Factories\MonthlyUsageStatFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'year',
        'month',
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
