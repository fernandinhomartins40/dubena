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

    // Geocoding (N2 — gate externo).
    'geocoding' => [
        'key' => env('GEOCODING_API_KEY'),
    ],

    // PIX (N7 — gate bancário, Itaú). Segredos só no .env.
    'pix' => [
        'enabled' => env('PIX_ENABLED', false),
        'psp' => env('PIX_PSP', 'itau'),
        'ambiente' => env('PIX_AMBIENTE', 'homologacao'),
        'webhook_secret' => env('PIX_WEBHOOK_SECRET'),
    ],

];
