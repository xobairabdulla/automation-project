<?php

namespace App\Services;

use App\Models\FacebookAccount;
use App\Models\FacebookPage;
use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class FacebookService
{
    private string $appId;

    private string $appSecret;

    private string $redirectUri;

    private string $graphVersion;

    private string $graphBase;

    public function __construct()
    {
        $this->appId = config('services.meta.app_id', '');
        $this->appSecret = config('services.meta.app_secret', '');
        $this->redirectUri = config('services.meta.redirect_uri', '');
        $this->graphVersion = config('services.meta.graph_api_version', 'v20.0');
        $this->graphBase = "https://graph.facebook.com/{$this->graphVersion}";
    }

    public function getOAuthUrl(string $state): string
    {
        $params = http_build_query([
            'client_id' => $this->appId,
            'redirect_uri' => $this->redirectUri,
            'scope' => 'pages_show_list,pages_read_engagement,pages_messaging,pages_manage_posts,pages_read_user_content,pages_manage_metadata',
            'response_type' => 'code',
            'state' => $state,
        ]);

        return "https://www.facebook.com/dialog/oauth?{$params}";
    }

    /**
     * @return array{access_token: string, token_type: string, expires_in?: int}
     */
    public function exchangeCodeForToken(string $code): array
    {
        $response = Http::get("{$this->graphBase}/oauth/access_token", [
            'client_id' => $this->appId,
            'client_secret' => $this->appSecret,
            'redirect_uri' => $this->redirectUri,
            'code' => $code,
        ]);

        $this->assertSuccess($response, 'Failed to exchange code for access token.');

        return $response->json();
    }

    /**
     * @return array{id: string, name: string}
     */
    public function getFacebookUser(string $accessToken): array
    {
        $response = Http::get("{$this->graphBase}/me", [
            'access_token' => $accessToken,
            'fields' => 'id,name',
        ]);

        $this->assertSuccess($response, 'Failed to fetch Facebook user.');

        return $response->json();
    }

    /**
     * @return array<int, array{id: string, name: string, access_token: string}>
     */
    public function getManagedPages(string $userAccessToken): array
    {
        $response = Http::get("{$this->graphBase}/me/accounts", [
            'access_token' => $userAccessToken,
            'fields' => 'id,name,access_token',
        ]);

        $this->assertSuccess($response, 'Failed to fetch managed pages.');

        return $response->json('data', []);
    }

    public function sendMessage(string $pageAccessToken, string $recipientId, string $text): Response
    {
        return Http::withToken($pageAccessToken)->post("{$this->graphBase}/me/messages", [
            'recipient' => ['id' => $recipientId],
            'message' => ['text' => $text],
            'messaging_type' => 'RESPONSE',
        ]);
    }

    public function sendImageAttachment(string $pageAccessToken, string $recipientId, string $imageUrl): Response
    {
        return Http::withToken($pageAccessToken)->post("{$this->graphBase}/me/messages", [
            'recipient' => ['id' => $recipientId],
            'message' => [
                'attachment' => [
                    'type' => 'image',
                    'payload' => [
                        'url' => $imageUrl,
                        'is_reusable' => true,
                    ],
                ],
            ],
            'messaging_type' => 'RESPONSE',
        ]);
    }

    /**
     * Send a Generic Template carousel (max 10 elements).
     *
     * @param  array<int, array<string, mixed>>  $elements
     */
    public function sendGenericTemplate(string $pageAccessToken, string $recipientId, array $elements): Response
    {
        return Http::withToken($pageAccessToken)->post("{$this->graphBase}/me/messages", [
            'recipient' => ['id' => $recipientId],
            'message' => [
                'attachment' => [
                    'type' => 'template',
                    'payload' => [
                        'template_type' => 'generic',
                        'elements' => array_slice($elements, 0, 10),
                    ],
                ],
            ],
            'messaging_type' => 'RESPONSE',
        ]);
    }

    public function replyToComment(string $pageAccessToken, string $commentId, string $text): Response
    {
        return Http::withToken($pageAccessToken)->post("{$this->graphBase}/{$commentId}/comments", [
            'message' => $text,
        ]);
    }

    public function subscribePageToWebhooks(FacebookPage $page): void
    {
        $response = Http::asForm()
            ->withToken($page->page_access_token_encrypted)
            ->post("{$this->graphBase}/{$page->page_id}/subscribed_apps", [
                'subscribed_fields' => 'messages,messaging_postbacks,feed',
            ]);

        $this->assertSuccess($response, 'Failed to subscribe page to Facebook webhooks.');
    }

    public function saveAccountAndPages(User $user, string $userAccessToken, ?int $expiresIn = null): FacebookAccount
    {
        $fbUser = $this->getFacebookUser($userAccessToken);

        $account = FacebookAccount::updateOrCreate(
            ['user_id' => $user->id, 'facebook_user_id' => $fbUser['id']],
            [
                'tenant_id' => $user->tenant_id,
                'name' => $fbUser['name'],
                'access_token_encrypted' => $userAccessToken,
                'token_expires_at' => $expiresIn ? now()->addSeconds($expiresIn) : null,
            ]
        );

        $pages = $this->getManagedPages($userAccessToken);

        foreach ($pages as $page) {
            FacebookPage::updateOrCreate(
                ['user_id' => $user->id, 'page_id' => $page['id']],
                [
                    'tenant_id' => $user->tenant_id,
                    'facebook_account_id' => $account->id,
                    'page_name' => $page['name'],
                    'page_access_token_encrypted' => $page['access_token'],
                ]
            );
        }

        return $account;
    }

    private function assertSuccess(Response $response, string $context): void
    {
        if ($response->failed()) {
            $error = $response->json('error.message', 'Unknown error');
            throw new \RuntimeException("{$context}: {$error}");
        }
    }
}
