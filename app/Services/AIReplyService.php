<?php

namespace App\Services;

use App\Models\AiPrompt;
use App\Models\AiProviderSetting;
use App\Models\AiReplyLog;
use App\Models\FacebookPage;
use App\Models\KnowledgeBase;
use App\Models\KnowledgeBaseItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIReplyService
{
    private const UNKNOWN_SENTINEL = '__UNKNOWN__';

    public function __construct(private readonly KnowledgeBaseMatcherService $matcher) {}

    public function generateReply(
        FacebookPage $page,
        string $question,
        ?int $conversationId = null,
        ?int $facebookCommentId = null,
    ): ?string {
        $knowledgeBase = KnowledgeBase::where('facebook_page_id', $page->id)
            ->where('status', 'active')
            ->with(['items' => fn ($q) => $q->where('status', 'active')->orderByDesc('priority')])
            ->first();

        $allItems = $knowledgeBase?->items ?? collect();

        // Find the most relevant KB items for this question
        $matchedItems = $this->matcher->findRelevant($question, $allItems);
        $this->matcher->logMatch($question, $matchedItems);

        // Use matched items if any; fall back to top 10 by priority for general context
        $itemsForPrompt = $matchedItems->isNotEmpty() ? $matchedItems : $allItems->take(10);

        Log::info('AIReplyService: KB matching result', [
            'page_id' => $page->id,
            'question' => mb_substr($question, 0, 120),
            'kb_total_items' => $allItems->count(),
            'matched_count' => $matchedItems->count(),
            'top_match' => $matchedItems->first()?->title,
            'top_score' => $matchedItems->first()?->getAttribute('_match_score'),
            'prompt_items' => $itemsForPrompt->count(),
            'fallback_to_all' => $matchedItems->isEmpty() && $allItems->isNotEmpty(),
        ]);

        $aiPrompt = AiPrompt::where('facebook_page_id', $page->id)->first();
        $systemPrompt = $this->buildSystemPrompt($itemsForPrompt, $aiPrompt);
        $model = $this->resolveModel();
        $apiKey = $this->resolveApiKey();
        $provider = $this->resolveProvider();

        if (! $apiKey) {
            if (config('app.env') === 'local') {
                $stub = "[TEST MODE] This is a simulated AI reply. Question received: \"{$question}\"";

                return $this->logAndReturn($page, $conversationId, $facebookCommentId, $systemPrompt, $stub, $model, 0, 'success', null);
            }

            return $this->logAndReturn($page, $conversationId, $facebookCommentId, $systemPrompt, null, $model, 0, 'failed', 'No API key configured.');
        }

        return match ($provider) {
            'anthropic' => $this->callAnthropic($page, $question, $conversationId, $facebookCommentId, $systemPrompt, $model, $apiKey, $aiPrompt),
            'gemini' => $this->callGemini($page, $question, $conversationId, $facebookCommentId, $systemPrompt, $model, $apiKey, $aiPrompt),
            default => $this->callOpenAi($page, $question, $conversationId, $facebookCommentId, $systemPrompt, $model, $apiKey, $aiPrompt),
        };
    }

    private function callAnthropic(
        FacebookPage $page,
        string $question,
        ?int $conversationId,
        ?int $facebookCommentId,
        string $systemPrompt,
        string $model,
        string $apiKey,
        ?AiPrompt $aiPrompt,
    ): ?string {
        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
            ])
                ->timeout(30)
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => $model,
                    'max_tokens' => $this->maxTokens($aiPrompt),
                    'system' => $systemPrompt,
                    'messages' => [
                        ['role' => 'user', 'content' => $question],
                    ],
                ]);

            if ($response->failed()) {
                $error = $response->json('error.message', 'Anthropic API error');
                Log::error('AIReplyService: Anthropic API failed', ['page_id' => $page->id, 'error' => $error]);

                return $this->logAndReturn($page, $conversationId, $facebookCommentId, $systemPrompt, null, $model, 0, 'failed', $error);
            }

            $replyText = $response->json('content.0.text', '');
            $tokensUsed = $response->json('usage.input_tokens', 0) + $response->json('usage.output_tokens', 0);

            if (str_contains($replyText, self::UNKNOWN_SENTINEL)) {
                Log::info('AIReplyService: Unknown answer — will use fallback', ['page_id' => $page->id]);

                return $this->logAndReturn($page, $conversationId, $facebookCommentId, $systemPrompt, $replyText, $model, $tokensUsed, 'unknown_answer', null);
            }

            $this->logAndReturn($page, $conversationId, $facebookCommentId, $systemPrompt, $replyText, $model, $tokensUsed, 'success', null);

            Log::info('AIReplyService: Reply generated', ['page_id' => $page->id, 'reply_length' => mb_strlen($replyText)]);

            return trim($replyText);
        } catch (\Throwable $e) {
            Log::error('AIReplyService: Anthropic exception', ['page_id' => $page->id, 'error' => $e->getMessage()]);

            return $this->logAndReturn($page, $conversationId, $facebookCommentId, $systemPrompt, null, $model, 0, 'failed', $e->getMessage());
        }
    }

    private function callGemini(
        FacebookPage $page,
        string $question,
        ?int $conversationId,
        ?int $facebookCommentId,
        string $systemPrompt,
        string $model,
        string $apiKey,
        ?AiPrompt $aiPrompt,
    ): ?string {
        try {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

            $response = Http::timeout(30)->post($url, [
                'system_instruction' => [
                    'parts' => [['text' => $systemPrompt]],
                ],
                'contents' => [
                    ['role' => 'user', 'parts' => [['text' => $question]]],
                ],
                'generationConfig' => [
                    'maxOutputTokens' => $this->maxTokens($aiPrompt),
                ],
            ]);

            if ($response->failed()) {
                $error = $response->json('error.message', 'Gemini API error');
                Log::error('AIReplyService: Gemini API failed', ['page_id' => $page->id, 'error' => $error]);

                return $this->logAndReturn($page, $conversationId, $facebookCommentId, $systemPrompt, null, $model, 0, 'failed', $error);
            }

            $replyText = $response->json('candidates.0.content.parts.0.text', '');
            $tokensUsed = $response->json('usageMetadata.totalTokenCount', 0);

            if (str_contains($replyText, self::UNKNOWN_SENTINEL)) {
                Log::info('AIReplyService: Unknown answer — will use fallback', ['page_id' => $page->id]);

                return $this->logAndReturn($page, $conversationId, $facebookCommentId, $systemPrompt, $replyText, $model, $tokensUsed, 'unknown_answer', null);
            }

            $this->logAndReturn($page, $conversationId, $facebookCommentId, $systemPrompt, $replyText, $model, $tokensUsed, 'success', null);

            Log::info('AIReplyService: Reply generated', ['page_id' => $page->id, 'reply_length' => mb_strlen($replyText)]);

            return trim($replyText);
        } catch (\Throwable $e) {
            Log::error('AIReplyService: Gemini exception', ['page_id' => $page->id, 'error' => $e->getMessage()]);

            return $this->logAndReturn($page, $conversationId, $facebookCommentId, $systemPrompt, null, $model, 0, 'failed', $e->getMessage());
        }
    }

    private function callOpenAi(
        FacebookPage $page,
        string $question,
        ?int $conversationId,
        ?int $facebookCommentId,
        string $systemPrompt,
        string $model,
        string $apiKey,
        ?AiPrompt $aiPrompt,
    ): ?string {
        try {
            $response = Http::withToken($apiKey)
                ->timeout(15)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $model,
                    'max_tokens' => $this->maxTokens($aiPrompt),
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $question],
                    ],
                ]);

            if ($response->failed()) {
                $error = $response->json('error.message', 'OpenAI API error');
                Log::error('AIReplyService: OpenAI API failed', ['page_id' => $page->id, 'error' => $error]);

                return $this->logAndReturn($page, $conversationId, $facebookCommentId, $systemPrompt, null, $model, 0, 'failed', $error);
            }

            $replyText = $response->json('choices.0.message.content', '');
            $tokensUsed = $response->json('usage.total_tokens', 0);

            if (str_contains($replyText, self::UNKNOWN_SENTINEL)) {
                Log::info('AIReplyService: Unknown answer — will use fallback', ['page_id' => $page->id]);

                return $this->logAndReturn($page, $conversationId, $facebookCommentId, $systemPrompt, $replyText, $model, $tokensUsed, 'unknown_answer', null);
            }

            $this->logAndReturn($page, $conversationId, $facebookCommentId, $systemPrompt, $replyText, $model, $tokensUsed, 'success', null);

            Log::info('AIReplyService: Reply generated', ['page_id' => $page->id, 'reply_length' => mb_strlen($replyText)]);

            return trim($replyText);
        } catch (\Throwable $e) {
            Log::error('AIReplyService: OpenAI exception', ['page_id' => $page->id, 'error' => $e->getMessage()]);

            return $this->logAndReturn($page, $conversationId, $facebookCommentId, $systemPrompt, null, $model, 0, 'failed', $e->getMessage());
        }
    }

    /**
     * @param  Collection<int, KnowledgeBaseItem>  $items
     */
    private function buildSystemPrompt(Collection $items, ?AiPrompt $aiPrompt): string
    {
        $tone = $aiPrompt?->tone ?? 'friendly';
        $useEmoji = $aiPrompt?->use_emoji ? 'You may use 1-2 relevant emojis.' : 'Do not use emojis.';
        $restricted = $aiPrompt?->restricted_instructions ? "\nADDITIONAL INSTRUCTIONS: {$aiPrompt->restricted_instructions}" : '';
        $sentinel = self::UNKNOWN_SENTINEL;

        $knowledgeSection = '';
        if ($items->isNotEmpty()) {
            $knowledgeSection = "\n\nKNOWLEDGE BASE (answer from these items ONLY):\n";
            foreach ($items as $item) {
                $knowledgeSection .= "[{$item->category}] {$item->title}: {$item->content}\n";
            }
        }

        return <<<PROMPT
You are a helpful customer support chatbot for a Facebook business page. Reply in a {$tone} tone. {$useEmoji}

LANGUAGE RULES (strictly follow):
- Detect the customer's language from their message.
- If they write in Bangla (বাংলা script) → reply in Bangla.
- If they write in Banglish (Bangla words using English letters, e.g. "delivery kobe dibo") → reply in Banglish.
- If they write in English → reply in English.
- Match the customer's language style naturally.

STRICT RULES (must follow):
1. Answer ONLY using the knowledge base items below. Do not add any information not present.
2. Never invent or guess prices, delivery times, stock availability, or policies.
3. Keep replies SHORT: 2-4 sentences maximum.
4. If you need more details to help (e.g. order number, product name, size), ask politely.
5. If the knowledge base does not contain a clear answer, respond with EXACTLY this text and nothing else:
   {$sentinel}
{$knowledgeSection}{$restricted}
PROMPT;
    }

    private function resolveProvider(): string
    {
        $setting = AiProviderSetting::active();

        return $setting?->provider_name ?? config('services.ai.provider', 'anthropic');
    }

    private function resolveModel(): string
    {
        $setting = AiProviderSetting::active();

        if ($setting?->model) {
            return $setting->model;
        }

        $provider = $this->resolveProvider();

        return match ($provider) {
            'anthropic' => config('services.ai.anthropic_model', 'claude-opus-4-7'),
            'gemini' => config('services.ai.gemini_model', 'gemini-1.5-flash'),
            default => config('services.ai.openai_model', 'gpt-4o-mini'),
        };
    }

    private function resolveApiKey(): ?string
    {
        $setting = AiProviderSetting::active();

        if ($setting?->api_key_encrypted) {
            return $setting->api_key_encrypted;
        }

        $provider = $this->resolveProvider();

        return match ($provider) {
            'anthropic' => config('services.ai.anthropic_api_key') ?: null,
            'gemini' => config('services.ai.gemini_api_key') ?: null,
            default => config('services.ai.openai_api_key') ?: null,
        };
    }

    private function maxTokens(?AiPrompt $aiPrompt): int
    {
        return match ($aiPrompt?->reply_length ?? 'short') {
            'long' => 400,
            'medium' => 200,
            default => 100,
        };
    }

    private function logAndReturn(
        FacebookPage $page,
        ?int $conversationId,
        ?int $facebookCommentId,
        string $prompt,
        ?string $response,
        string $model,
        int $tokensUsed,
        string $status,
        ?string $errorMessage,
    ): ?string {
        AiReplyLog::create([
            'tenant_id' => $page->user_id,
            'facebook_page_id' => $page->id,
            'conversation_id' => $conversationId,
            'facebook_comment_id' => $facebookCommentId,
            'prompt' => $prompt,
            'response' => $response,
            'model' => $model,
            'tokens_used' => $tokensUsed,
            'status' => $status,
            'error_message' => $errorMessage,
        ]);

        return null;
    }
}
