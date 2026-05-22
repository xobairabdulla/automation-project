<?php

namespace App\Jobs;

use App\Models\CommentReply;
use App\Models\FacebookComment;
use App\Models\FacebookPage;
use App\Services\FacebookService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendFacebookCommentReplyJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly FacebookPage $page,
        public readonly FacebookComment $comment,
        public readonly string $replyText
    ) {}

    public function handle(FacebookService $facebookService): void
    {
        try {
            $response = $facebookService->replyToComment(
                $this->page->page_access_token_encrypted,
                $this->comment->external_comment_id,
                $this->replyText
            );

            if ($response->failed()) {
                $error = $response->json('error.message', 'Unknown error');

                Log::error('SendFacebookCommentReplyJob: send failed', [
                    'comment_id' => $this->comment->id,
                    'error' => $error,
                ]);

                CommentReply::create([
                    'tenant_id' => $this->comment->tenant_id,
                    'facebook_comment_id' => $this->comment->id,
                    'reply_text' => $this->replyText,
                    'status' => 'failed',
                    'error_message' => $error,
                ]);

                $this->comment->update(['status' => 'failed']);

                return;
            }

            $externalReplyId = $response->json('id');

            CommentReply::create([
                'tenant_id' => $this->comment->tenant_id,
                'facebook_comment_id' => $this->comment->id,
                'reply_text' => $this->replyText,
                'external_reply_id' => $externalReplyId,
                'status' => 'sent',
            ]);

            $this->comment->update(['status' => 'replied']);
        } catch (\Throwable $e) {
            Log::error('SendFacebookCommentReplyJob: exception', [
                'comment_id' => $this->comment->id,
                'error' => $e->getMessage(),
            ]);

            CommentReply::create([
                'tenant_id' => $this->comment->tenant_id,
                'facebook_comment_id' => $this->comment->id,
                'reply_text' => $this->replyText,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            $this->comment->update(['status' => 'failed']);
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('SendFacebookCommentReplyJob failed', [
            'comment_id' => $this->comment->id,
            'error' => $e->getMessage(),
        ]);
    }
}
