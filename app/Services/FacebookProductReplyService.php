<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\ConversationProductContext;
use App\Models\FacebookComment;
use App\Models\FacebookPage;
use App\Models\ProductCache;
use Illuminate\Support\Facades\Log;

class FacebookProductReplyService
{
    public function __construct(
        private readonly FacebookService $facebookService,
        private readonly ProductImageFetcherService $fetcherService,
        private readonly ConversationProductContextService $contextService,
        private readonly ProductMatcherService $matcherService,
    ) {}

    /**
     * Handle a product inquiry from a Messenger message.
     * Returns true when product images were sent (caller should skip default reply).
     */
    public function handleMessengerInquiry(
        FacebookPage $page,
        Conversation $conversation,
        string $senderPsid,
        string $messageText,
    ): bool {
        $userId = $page->user_id;

        // Check if this is a follow-up on the last product (more images / price)
        $context = $this->contextService->getContext($userId, $page->page_id, $senderPsid);

        if ($context && $this->matcherService->hasMoreImagesIntent($messageText)) {
            return $this->replyWithSavedContext($page, $conversation, $senderPsid, $context, $userId);
        }

        // Fresh product inquiry
        $result = $this->fetcherService->fetchForMessage($userId, $messageText);

        if ($result === null) {
            return false; // No source or no matching products — fall through to default reply
        }

        /** @var ProductCache[] $products */
        $products = $result['products'];
        $maxImages = $result['max_images'];

        $sent = $this->sendProductsToMessenger($page, $conversation, $senderPsid, $products, $maxImages, $userId);

        return $sent;
    }

    /**
     * Handle a product inquiry from a Facebook comment.
     * For comments: reply publicly asking buyer to check inbox, then DM the images.
     */
    public function handleCommentInquiry(
        FacebookPage $page,
        FacebookComment $comment,
        string $commenterId,
    ): bool {
        $userId = $page->user_id;
        $messageText = $comment->comment_text ?? '';

        $result = $this->fetcherService->fetchForMessage($userId, $messageText);

        if ($result === null) {
            return false;
        }

        /** @var ProductCache[] $products */
        $products = $result['products'];
        $maxImages = $result['max_images'];

        // Reply publicly on the comment
        $redirectText = config('product_automation.comment_redirect_text');
        $this->facebookService->replyToComment(
            $page->page_access_token_encrypted,
            $comment->external_comment_id,
            $redirectText,
        );

        // Try to send images via Messenger (requires the user to have previously messaged the page)
        $this->sendProductsToMessengerSilently($page, $commenterId, $products, $maxImages, $userId, $page->page_id);

        return true;
    }

    private function sendProductsToMessenger(
        FacebookPage $page,
        Conversation $conversation,
        string $recipientId,
        array $products,
        int $maxImages,
        int $userId,
    ): bool {
        $elements = $this->fetcherService->buildTemplateElements($products, $maxImages);

        if (empty($elements)) {
            // Fall back to sending individual image messages
            return $this->sendIndividualImages($page, $recipientId, $products, $maxImages, $userId, $page->page_id, 'message');
        }

        // Intro text
        $response = $this->facebookService->sendMessage(
            $page->page_access_token_encrypted,
            $recipientId,
            'এখানে কিছু প্রোডাক্ট দেখুন:',
        );

        if ($response->failed()) {
            Log::warning('FacebookProductReplyService: intro text failed', [
                'conversation_id' => $conversation->id,
                'error' => $response->json('error.message'),
            ]);
        }

        // Generic Template carousel
        $templateResponse = $this->facebookService->sendGenericTemplate(
            $page->page_access_token_encrypted,
            $recipientId,
            $elements,
        );

        if ($templateResponse->failed()) {
            Log::error('FacebookProductReplyService: generic template failed', [
                'conversation_id' => $conversation->id,
                'error' => $templateResponse->json('error.message'),
            ]);

            // Fallback to individual images
            return $this->sendIndividualImages($page, $recipientId, $products, $maxImages, $userId, $page->page_id, 'message');
        }

        $this->saveContextFromProducts($userId, $page->page_id, $recipientId, 'message', $products);

        return true;
    }

    private function sendProductsToMessengerSilently(
        FacebookPage $page,
        string $recipientId,
        array $products,
        int $maxImages,
        int $userId,
        string $pageId,
    ): void {
        try {
            $elements = $this->fetcherService->buildTemplateElements($products, $maxImages);

            if (! empty($elements)) {
                $this->facebookService->sendGenericTemplate(
                    $page->page_access_token_encrypted,
                    $recipientId,
                    $elements,
                );
            } else {
                $this->sendIndividualImages($page, $recipientId, $products, $maxImages, $userId, $pageId, 'comment');
            }

            $this->saveContextFromProducts($userId, $pageId, $recipientId, 'comment', $products);
        } catch (\Throwable $e) {
            Log::warning('FacebookProductReplyService: DM to commenter failed (may not have Messenger permission)', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendIndividualImages(
        FacebookPage $page,
        string $recipientId,
        array $products,
        int $maxImages,
        int $userId,
        string $pageId,
        string $channelType,
    ): bool {
        $imageUrls = $this->fetcherService->collectImageUrls($products, $maxImages);

        if (empty($imageUrls)) {
            return false;
        }

        $anySent = false;

        foreach ($imageUrls as $url) {
            $response = $this->facebookService->sendImageAttachment(
                $page->page_access_token_encrypted,
                $recipientId,
                $url,
            );

            if ($response->successful()) {
                $anySent = true;
            } else {
                Log::warning('FacebookProductReplyService: image send failed', [
                    'url' => $url,
                    'error' => $response->json('error.message'),
                ]);
            }
        }

        if ($anySent) {
            $this->saveContextFromProducts($userId, $pageId, $recipientId, $channelType, $products);
        }

        return $anySent;
    }

    private function replyWithSavedContext(
        FacebookPage $page,
        Conversation $conversation,
        string $recipientId,
        ConversationProductContext $context,
        int $userId,
    ): bool {
        $images = $context->last_sent_images ?? [];

        if (empty($images)) {
            return false;
        }

        foreach ($images as $url) {
            $this->facebookService->sendImageAttachment(
                $page->page_access_token_encrypted,
                $recipientId,
                $url,
            );
        }

        return true;
    }

    /**
     * @param  ProductCache[]  $products
     */
    private function saveContextFromProducts(
        int $userId,
        string $pageId,
        string $facebookUserId,
        string $channelType,
        array $products,
    ): void {
        if (empty($products)) {
            return;
        }

        $firstProduct = $products[0];
        $sentImages = $this->fetcherService->collectImageUrls($products, 10);

        $this->contextService->saveContext(
            $userId,
            $pageId,
            $facebookUserId,
            $channelType,
            $firstProduct,
            $sentImages,
        );
    }
}
