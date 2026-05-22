<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model
{
    /** @use HasFactory<\Database\Factories\EmailLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'to_email',
        'subject',
        'status',
        'error_message',
    ];

    public static function record(
        string $toEmail,
        string $subject,
        string $status = 'sent',
        ?int $userId = null,
        ?int $tenantId = null,
        ?string $errorMessage = null
    ): self {
        return self::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'to_email' => $toEmail,
            'subject' => $subject,
            'status' => $status,
            'error_message' => $errorMessage,
        ]);
    }
}
