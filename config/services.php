<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'whatsapp' => [
        'graph_api_base_url' => env('META_GRAPH_API_BASE_URL', 'https://graph.facebook.com/v23.0'),
        'business_account_id' => env('META_WHATSAPP_BUSINESS_ACCOUNT_ID'),
        'phone_number_id' => env('META_WHATSAPP_PHONE_NUMBER_ID'),
        'access_token' => env('META_WHATSAPP_ACCESS_TOKEN'),
        'webhook_verify_token' => env('META_WHATSAPP_WEBHOOK_VERIFY_TOKEN'),
        'app_secret' => env('META_APP_SECRET'),
        'send_delay_seconds' => (int) env('WHATSAPP_SEND_DELAY_SECONDS', 1),
        'retry_backoff_seconds' => env('WHATSAPP_RETRY_BACKOFF_SECONDS', '60,300,900'),
        'max_attempts' => (int) env('WHATSAPP_MAX_ATTEMPTS', 3),
        'daily_unique_recipient_limit' => (int) env('WHATSAPP_DAILY_UNIQUE_RECIPIENT_LIMIT', 250),
        'daily_unique_recipient_limit_enabled' => (bool) env('WHATSAPP_DAILY_UNIQUE_RECIPIENT_LIMIT_ENABLED', true),
    ],

];
