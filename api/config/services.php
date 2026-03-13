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
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'evomi' => [
        'key' => env('EVOMI_API_KEY'),
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'vat_percentage' => env('STRIPE_VAT_PERCENTAGE', 22),
    ],

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
    ],

    'cryptomus' => [
        'merchant_id'    => env('CRYPTOMUS_MERCHANT_ID'),
        'api_key'        => env('CRYPTOMUS_API_KEY'),
        'webhook_secret' => env('CRYPTOMUS_WEBHOOK_SECRET'),
        'vat_percentage' => env('CRYPTOMUS_VAT_PERCENTAGE', 0),
    ],

    'manual' => [
        'vat_percentage' => env('MANUAL_VAT_PERCENTAGE', 0),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
        'indexing_key' => env('GOOGLE_INDEXING_CRYPTO_KEY'),
    ],

    'firebase' => [
        'credentials' => env('FIREBASE_CREDENTIALS'),
    ],

    'coinbase' => [
        'key' => env('COINBASE_API_KEY'),
        'webhook_secret' => env('COINBASE_WEBHOOK_SECRET'),
        'vat_percentage' => env('COINBASE_VAT_PERCENTAGE', 0),
    ],

];
