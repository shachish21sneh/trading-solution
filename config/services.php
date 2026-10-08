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

    'angelone' => [
        'api_key' => env('ANGELONE_API_KEY'),
        'client_code' => env('ANGELONE_CLIENT_CODE'),
        'password' => env('ANGELONE_PASSWORD'),
        'totp_secret' => env('ANGELONE_TOTP_SECRET'),
        'jwt_token' => env('ANGELONE_JWT_TOKEN'),
        'feed_token' => env('ANGELONE_FEED_TOKEN'),
    ],

    'upstox' => [
        'api_key' => env('UPSTOX_API_KEY'),
        'api_secret' => env('UPSTOX_API_SECRET'),
        'access_token' => env('UPSTOX_ACCESS_TOKEN'),
    ],

    'zerodha' => [
        'api_key' => env('KITE_API_KEY'),
        'api_secret' => env('KITE_API_SECRET'),
        'access_token' => env('KITE_ACCESS_TOKEN'),
    ],

];
