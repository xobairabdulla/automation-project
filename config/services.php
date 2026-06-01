<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'meta' => [
        'app_id' => env('META_APP_ID'),
        'app_secret' => env('META_APP_SECRET'),
        'redirect_uri' => env('META_REDIRECT_URI', 'http://localhost:8000/facebook/callback'),
        'webhook_verify_token' => env('FACEBOOK_WEBHOOK_VERIFY_TOKEN', env('META_WEBHOOK_VERIFY_TOKEN')),
        'graph_api_version' => env('META_GRAPH_API_VERSION', 'v20.0'),
    ],

    'ai' => [
        'provider' => env('AI_PROVIDER', 'anthropic'),
        'anthropic_api_key' => env('ANTHROPIC_API_KEY'),
        'anthropic_model' => env('ANTHROPIC_MODEL', 'claude-opus-4-7'),
        'openai_api_key' => env('OPENAI_API_KEY'),
        'openai_model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'gemini_api_key' => env('GEMINI_API_KEY'),
        'gemini_model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
    ],

    'payments' => [
        'gateway' => env('PAYMENT_GATEWAY', 'stripe'),
        'stripe_key' => env('STRIPE_KEY'),
        'stripe_secret' => env('STRIPE_SECRET'),
        'stripe_webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'sslcommerz' => [
        'store_id' => env('SSLCOMMERZ_STORE_ID'),
        'store_password' => env('SSLCOMMERZ_STORE_PASSWORD'),
        'sandbox' => env('SSLCOMMERZ_SANDBOX', true),
        'currency' => env('SSLCOMMERZ_CURRENCY', 'BDT'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
