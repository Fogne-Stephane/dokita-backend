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

    // MTN MoMo
'mtn' => [
    'base_url'         => env('MTN_MOMO_BASE_URL', 'https://sandbox.momodeveloper.mtn.com'),
    'subscription_key' => env('MTN_MOMO_SUBSCRIPTION_KEY'),
    'api_user'         => env('MTN_MOMO_API_USER'),
    'api_key'          => env('MTN_MOMO_API_KEY'),
    'environment'      => env('MTN_MOMO_ENVIRONMENT', 'sandbox'),
    'currency'         => env('MTN_MOMO_CURRENCY', 'XAF'),
    'callback_url'     => env('MTN_MOMO_CALLBACK_URL'),
],

// Orange Money
'orange' => [
    'base_url'       => env('ORANGE_MONEY_BASE_URL'),
    'client_id'      => env('ORANGE_MONEY_CLIENT_ID'),
    'client_secret'  => env('ORANGE_MONEY_CLIENT_SECRET'),
    'merchant_key'   => env('ORANGE_MONEY_MERCHANT_KEY'),
    'callback_url'   => env('ORANGE_MONEY_CALLBACK_URL'),
],

];
