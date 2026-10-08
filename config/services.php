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

    'email' => [
        'provider' => env('EMAIL_PROVIDER', env('MAIL_MAILER', 'brevo')),
        'api_key' => env('BREVO_API_KEY', env('EMAIL_API_KEY')),
        'from_address' => env('EMAIL_FROM_ADDRESS', env('MAIL_FROM_ADDRESS', 'noreply@larable.dev')),
        'from_name' => env('EMAIL_FROM_NAME', env('MAIL_FROM_NAME', 'NAAP Document Routing')),
    ],

    'sms' => [
        'enabled' => env('SMS_ENABLED', false),
        'driver' => env('SMS_DRIVER', 'none'),
        'semaphore' => [
            'api_key' => env('SEMAPHORE_API_KEY'),
            'sender_name' => env('SEMAPHORE_SENDER_NAME', 'NAAP'),
        ],
        'twilio' => [
            'sid' => env('TWILIO_SID'),
            'token' => env('TWILIO_AUTH_TOKEN'),
            'from' => env('TWILIO_FROM'),
        ],
    ],

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'bot_username' => env('TELEGRAM_BOT_USERNAME', 'NAAPRoutingBot'),
    ],
];

