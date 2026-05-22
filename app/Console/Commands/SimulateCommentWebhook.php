<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\Facebook\FacebookWebhookController;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class SimulateCommentWebhook extends Command
{
    protected $signature = 'simulate:comment
                            {--page-id= : Facebook page ID (defaults to first connected page)}
                            {--commenter-id=987654321 : Commenter PSID}
                            {--commenter-name=Test User : Commenter display name}
                            {--message=Great post! : Comment text}
                            {--post-id= : Post ID to attach comment to (defaults to random)}';

    protected $description = 'Simulate an incoming Facebook comment webhook payload for local testing';

    public function handle(FacebookWebhookController $controller): int
    {
        $pageId = $this->option('page-id');

        if (! $pageId) {
            $page = \App\Models\FacebookPage::where('is_connected', true)->first();

            if (! $page) {
                $this->error('No connected Facebook page found. Pass --page-id=YOUR_PAGE_ID');

                return self::FAILURE;
            }

            $pageId = $page->page_id;
            $this->line("Using page: {$page->page_name} ({$pageId})");
        }

        $commenterId = $this->option('commenter-id');
        $commenterName = $this->option('commenter-name');
        $messageText = $this->option('message');
        $postId = $this->option('post-id') ?? 'post_'.now()->timestamp;
        $commentId = 'comment_'.now()->timestamp.'_simulated';

        $payload = [
            'object' => 'page',
            'entry' => [
                [
                    'id' => $pageId,
                    'time' => now()->timestamp,
                    'changes' => [
                        [
                            'field' => 'feed',
                            'value' => [
                                'item' => 'comment',
                                'comment_id' => $commentId,
                                'post_id' => $postId,
                                'from' => [
                                    'id' => $commenterId,
                                    'name' => $commenterName,
                                ],
                                'message' => $messageText,
                                'created_time' => now()->timestamp,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->info('Sending simulated comment webhook...');
        $this->line('  Page ID:   '.$pageId);
        $this->line('  Commenter: '.$commenterName.' ('.$commenterId.')');
        $this->line('  Comment:   '.$messageText);

        $bodyJson = json_encode($payload);

        $request = Request::create('/api/webhooks/facebook', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-Hub-Signature-256' => 'sha256=skipped',
        ], $bodyJson);

        config(['services.meta.app_secret' => null]);

        $response = $controller->receive($request);

        if ($response->getStatusCode() === 200) {
            $this->info('Webhook received successfully. Check queue/jobs for processing.');
        } else {
            $this->error('Webhook returned status: '.$response->getStatusCode());
            $this->error($response->getContent());
        }

        return self::SUCCESS;
    }
}
