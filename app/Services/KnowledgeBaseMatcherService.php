<?php

namespace App\Services;

use App\Models\KnowledgeBaseItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class KnowledgeBaseMatcherService
{
    /**
     * Category-to-phrase hints for broad topic detection.
     * Covers Bangla, Banglish, and English patterns.
     *
     * @var array<string, list<string>>
     */
    private const CATEGORY_HINTS = [
        'delivery' => [
            'delivery', 'deliver', 'shipping', 'ship', 'courier', 'dibo', 'deben', 'pathaben',
            'ডেলিভারি', 'শিপিং', 'কুরিয়ার', 'পাঠাবেন', 'আসবে', 'পাবো', 'পাব', 'পৌঁছাবে', 'কত দিনে',
        ],
        'payment' => [
            'payment', 'pay', 'bkash', 'nagad', 'rocket', 'cash', 'cod', 'upay',
            'পেমেন্ট', 'টাকা', 'বিকাশ', 'নগদ', 'রকেট', 'ক্যাশ', 'পরিশোধ',
        ],
        'order' => [
            'order', 'buy', 'purchase', 'booking', 'book', 'korte chai', 'dite parben',
            'অর্ডার', 'কিনতে', 'কিনব', 'কিনবো', 'কিনতে চাই', 'বুকিং',
        ],
        'product' => [
            'product', 'item', 'stock', 'available', 'size', 'color', 'colour', 'ache ki',
            'পণ্য', 'পেতে', 'পাওয়া', 'আছে', 'স্টক', 'সাইজ', 'কালার', 'রঙ',
        ],
        'complaint' => [
            'complaint', 'problem', 'issue', 'wrong', 'bad', 'damaged', 'broken', 'chole na',
            'অভিযোগ', 'সমস্যা', 'নষ্ট', 'ভাঙা', 'ভুল', 'খারাপ', 'কাজ করে না',
        ],
        'support' => [
            'help', 'support', 'assist', 'contact', 'call', 'number', 'phone',
            'সাহায্য', 'হেল্প', 'সাপোর্ট', 'যোগাযোগ', 'নম্বর', 'ফোন',
        ],
        'faq' => [
            'how', 'what', 'when', 'where', 'why', 'which', 'tell me', 'janaben',
            'কিভাবে', 'কি', 'কখন', 'কোথায়', 'কেন', 'কোনটি', 'জানাবেন', 'বলবেন',
        ],
        'instruction' => [
            'how to', 'steps', 'process', 'guide', 'tutorial', 'kivabe korbo',
            'কিভাবে করব', 'পদ্ধতি', 'ধাপ', 'প্রক্রিয়া',
        ],
        'pricing' => [
            'price', 'cost', 'rate', 'fee', 'charge', 'daam koto', 'koto taka',
            'দাম', 'মূল্য', 'কত', 'রেট', 'চার্জ', 'দাম কত',
        ],
        'refund' => [
            'refund', 'return', 'exchange', 'ফেরত', 'রিফান্ড', 'ফেরত দেওয়া', 'বদলানো',
        ],
    ];

    /**
     * Score every item and return the top N with score > 0, sorted by score descending.
     *
     * @param  Collection<int, KnowledgeBaseItem>  $items
     * @return Collection<int, KnowledgeBaseItem>
     */
    public function findRelevant(string $message, Collection $items, int $topN = 5): Collection
    {
        if ($items->isEmpty()) {
            return collect();
        }

        $normalized = $this->normalize($message);
        $words = $this->tokenize($normalized);

        $scored = $items
            ->map(function (KnowledgeBaseItem $item) use ($normalized, $words) {
                $score = $this->score($item, $normalized, $words);
                $item->setAttribute('_match_score', $score);

                return $item;
            })
            ->filter(fn (KnowledgeBaseItem $item) => $item->getAttribute('_match_score') > 0)
            ->sortByDesc(fn (KnowledgeBaseItem $item) => $item->getAttribute('_match_score'))
            ->take($topN)
            ->values();

        return $scored;
    }

    private function score(KnowledgeBaseItem $item, string $normalizedMessage, array $messageWords): int
    {
        $score = 0;

        // 1. Keyword / trigger-phrase match (+30 each)
        foreach ($item->keywords ?? [] as $kw) {
            $kw = $this->normalize((string) $kw);
            if ($kw !== '' && str_contains($normalizedMessage, $kw)) {
                $score += 30;
            }
        }

        // 2. Title word overlap (+10 per word ≥ 3 chars)
        foreach ($this->tokenize($this->normalize($item->title)) as $tw) {
            if (mb_strlen($tw, 'UTF-8') >= 3 && in_array($tw, $messageWords, true)) {
                $score += 10;
            }
        }

        // 3. Category hint match (+5)
        if ($this->categoryMatchesMessage($item->category, $normalizedMessage)) {
            $score += 5;
        }

        // 4. Content word overlap (+2 per word, capped at 20)
        $contentScore = 0;
        foreach (array_unique($this->tokenize($this->normalize($item->content))) as $cw) {
            if (mb_strlen($cw, 'UTF-8') >= 3 && in_array($cw, $messageWords, true)) {
                $contentScore += 2;
                if ($contentScore >= 20) {
                    break;
                }
            }
        }
        $score += $contentScore;

        // 5. Priority bonus (+5 per priority level)
        $score += ($item->priority ?? 0) * 5;

        return $score;
    }

    private function categoryMatchesMessage(string $category, string $normalizedMessage): bool
    {
        foreach (self::CATEGORY_HINTS[$category] ?? [] as $hint) {
            if (str_contains($normalizedMessage, mb_strtolower($hint, 'UTF-8'))) {
                return true;
            }
        }

        return false;
    }

    private function normalize(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        // Keep Unicode letters (covers Bangla, Latin, etc.), digits, and spaces
        $text = (string) preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);

        return trim((string) preg_replace('/\s+/', ' ', $text));
    }

    /**
     * @return list<string>
     */
    private function tokenize(string $text): array
    {
        if ($text === '') {
            return [];
        }

        return array_values(array_filter(
            (array) preg_split('/\s+/u', $text),
            fn (string $w) => mb_strlen($w, 'UTF-8') >= 2
        ));
    }

    public function logMatch(string $message, Collection $matched): void
    {
        if ($matched->isEmpty()) {
            Log::info('KnowledgeBaseMatcherService: No items matched', [
                'message' => mb_substr($message, 0, 100),
            ]);

            return;
        }

        Log::info('KnowledgeBaseMatcherService: Matched items', [
            'message' => mb_substr($message, 0, 100),
            'matches' => $matched->map(fn (KnowledgeBaseItem $i) => [
                'id' => $i->id,
                'title' => $i->title,
                'category' => $i->category,
                'score' => $i->getAttribute('_match_score'),
            ])->all(),
        ]);
    }
}
