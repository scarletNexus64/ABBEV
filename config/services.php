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

    /*
    |--------------------------------------------------------------------------
    | Bunny Stream (hébergement + transcodage HLS des films/séries)
    |--------------------------------------------------------------------------
    */
    'bunny' => [
        'library_id'   => env('BUNNY_STREAM_LIBRARY_ID'),
        'api_key'      => env('BUNNY_STREAM_API_KEY'),
        'cdn_hostname' => env('BUNNY_STREAM_CDN_HOSTNAME'), // ex: vz-xxxxxxxx.b-cdn.net
        'token_key'    => env('BUNNY_STREAM_TOKEN_KEY'),    // pour signed URLs
        'signed_urls'  => env('BUNNY_STREAM_SIGNED_URLS', true),
        'token_ttl'    => (int) env('BUNNY_STREAM_TOKEN_TTL', 3600), // secondes
    ],

    /*
    |--------------------------------------------------------------------------
    | KPay (paiements & retraits Mobile Money — USSD ou passerelle hébergée)
    |--------------------------------------------------------------------------
    | La configuration effective lue par le SDK reste config/kpay.php.
    | Ce bloc sert de référence centralisée des variables d'environnement.
    */
    'kpay' => [
        'base_url'       => env('KPAY_BASE_URL', 'https://admin.kpay.site'),
        'api_key'        => env('KPAY_API_KEY'),
        'secret_key'     => env('KPAY_SECRET_KEY'),
        'gateway_secret' => env('KPAY_GATEWAY_SECRET'),
        'max_duration'   => (int) env('KPAY_MAX_DURATION', 300),
    ],

];
