<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\User;
use App\Notifications\HumanHandoverNotification;

class HumanHandoverService
{
    private const SENSITIVE_PATTERNS = [
        'angry' => ['angry', 'furious', 'upset', 'disgusted', 'hate', 'terrible', 'horrible', 'worst', 'awful'],
        'refund' => ['refund', 'money back', 'return money', 'reimburse', 'reimbursement', 'chargeback'],
        'complaint' => ['complaint', 'complain', 'unacceptable', 'disappointed', 'disgusting', 'sue', 'lawyer', 'legal action'],
        'payment_issue' => ['payment failed', 'charge failed', 'double charged', 'overcharged', 'billing issue'],
        'delivery_problem' => ['not delivered', 'never arrived', 'still waiting', 'where is my order', 'lost package', 'wrong item'],
        'human_request' => ['speak to human', 'talk to person', 'real person', 'live agent', 'customer service', 'representative', 'speak to someone'],
        'bad_language' => ['damn', 'hell', 'stupid', 'idiot', 'scam', 'fraud', 'rip off', 'ripoff'],
    ];

    public function detectSensitiveTrigger(string $text): ?string
    {
        $lower = mb_strtolower($text);

        foreach (self::SENSITIVE_PATTERNS as $reason => $keywords) {
            foreach ($keywords as $keyword) {
                $pattern = '/\b'.preg_quote($keyword, '/').'\b/u';
                if (preg_match($pattern, $lower)) {
                    return $reason;
                }
            }
        }

        return null;
    }

    public function triggerHandover(
        Conversation $conversation,
        string $reason,
        string $triggeredBy = 'automation',
        ?int $triggeredByUserId = null,
    ): void {
        if ($conversation->human_takeover) {
            return;
        }

        $conversation->update([
            'human_takeover' => true,
            'human_takeover_reason' => $reason,
            'human_takeover_by' => $triggeredByUserId,
            'human_takeover_at' => now(),
        ]);

        $owner = User::find($conversation->tenant_id);
        if ($owner) {
            $owner->notify(new HumanHandoverNotification($conversation, $reason, $triggeredBy));
        }
    }

    public function releaseHandover(Conversation $conversation): void
    {
        $conversation->update([
            'human_takeover' => false,
            'human_takeover_reason' => null,
            'human_takeover_by' => null,
            'human_takeover_at' => null,
        ]);
    }
}
