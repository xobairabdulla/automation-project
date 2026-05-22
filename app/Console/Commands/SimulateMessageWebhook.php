<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\Facebook\FacebookWebhookController;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class SimulateMessageWebhook extends Command
{
    protected $signature = 'simulate:message
                            {--page-id= : Facebook page ID (defaults to first connected page)}
                            {--sender-id=123456789 : Sender PSID}
                            {--message=Hello! : Message text}';

    protected $description = 'Simulate an incoming Facebook Messenger webhook payload for local testing';

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

        $senderId = $this->option('sender-id');
        $messageText = $this->option('message');
        $mid = 'mid.'.now()->timestamp.'.simulated_message';

        $payload = [
            'object' => 'page',
            'entry' => [
                [
                    'id' => $pageId,
                    'time' => now()->timestamp,
                    'messaging' => [
                        [
                            'sender' => ['id' => $senderId],
                            'recipient' => ['id' => $pageId],
                            'timestamp' => now()->timestamp * 1000,
                            'message' => [
                                'mid' => $mid,
                                'text' => $messageText,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->info('Sending simulated message webhook...');
        $this->line('  Page ID: '.$pageId);
        $this->line('  Sender:  '.$senderId);
        $this->line('  Message: '.$messageText);

        $bodyJson = json_encode($payload);
        $secret = config('services.meta.app_secret');
        $signature = $secret ? 'sha256='.hash_hmac('sha256', $bodyJson, $secret) : null;

        $request = Request::create('/api/webhooks/facebook', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-Hub-Signature-256' => $signature ?? 'sha256=skipped',
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
