<?php

namespace App\Services\Facebook;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FacebookUserProfileService
{
    private const GRAPH_VERSION = 'v20.0';

    private const CACHE_HOURS = 24;

    /**
     * Fetch a Messenger sender profile from the Meta Graph API.
     *
     * Returns null on any failure so callers can degrade gracefully.
     * Never logs the access token.
     *
     * @return array{psid:string,first_name:string|null,last_name:string|null,full_name:string|null,profile_pic:string|null,locale:string|null,timezone:int|null}|null
     */
    public function fetchProfile(string $psid, string $pageAccessToken): ?array
    {
        if (! $psid || ! $pageAccessToken) {
            Log::warning('FacebookUserProfileService: Missing PSID or page token', ['psid' => $psid]);

            return null;
        }

        $cacheKey = "fb_profile_{$psid}";

        if (Cache::has($cacheKey)) {
            Log::debug('FacebookUserProfileService: Returning cached profile', ['psid' => $psid]);

            return Cache::get($cacheKey);
        }

        Log::info('FacebookUserProfileService: Fetching profile from Meta API', ['psid' => $psid]);

        try {
            $response = Http::timeout(8)->get(
                'https://graph.facebook.com/'.self::GRAPH_VERSION.'/'.$psid,
                [
                    'fields' => 'first_name,last_name,profile_pic,locale,timezone',
                    'access_token' => $pageAccessToken,
                ]
            );

            if ($response->failed()) {
                $error = $response->json('error.message') ?? 'Unknown Meta API error';
                Log::warning('FacebookUserProfileService: Profile fetch failed', [
                    'psid' => $psid,
                    'http_status' => $response->status(),
                    'meta_error' => $error,
                ]);

                return null;
            }

            $data = $response->json();

            $firstName = $data['first_name'] ?? null;
            $lastName = $data['last_name'] ?? null;
            $fullName = trim($firstName.' '.$lastName);

            $profile = [
                'psid' => $psid,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'full_name' => $fullName !== '' ? $fullName : null,
                'profile_pic' => $data['profile_pic'] ?? null,
                'locale' => $data['locale'] ?? null,
                'timezone' => isset($data['timezone']) ? (int) $data['timezone'] : null,
            ];

            Cache::put($cacheKey, $profile, now()->addHours(self::CACHE_HOURS));

            Log::info('FacebookUserProfileService: Profile fetched successfully', [
                'psid' => $psid,
                'name' => $profile['full_name'] ?? '(no name)',
            ]);

            return $profile;
        } catch (\Throwable $e) {
            Log::error('FacebookUserProfileService: Exception during profile fetch', [
                'psid' => $psid,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function bustCache(string $psid): void
    {
        Cache::forget("fb_profile_{$psid}");
    }
}
