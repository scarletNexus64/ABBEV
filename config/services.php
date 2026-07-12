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
        // Téléchargement offline (app mobile) : qualité max servie et
        // durée de validité de l'URL signée renvoyée par /watch/.../download.
        // Plus longue que `token_ttl` car un téléchargement peut être lent
        // et reprendre en arrière-plan.
        'download_max_height' => (int) env('BUNNY_DOWNLOAD_MAX_HEIGHT', 720),
        'download_token_ttl'  => (int) env('BUNNY_DOWNLOAD_TOKEN_TTL', 21600), // 6h
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

    /*
    |--------------------------------------------------------------------------
    | ExchangeRate-API (taux de change live des devises)
    |--------------------------------------------------------------------------
    | Alimente currencies.rate_from_xof via `php artisan rates:update` (planifié
    | quotidiennement). Base = XOF (devise de référence de la plateforme).
    | Clé : https://www.exchangerate-api.com/ (plan gratuit ~1 maj/jour).
    */
    'exchangerate' => [
        'key'  => env('EXCHANGERATE_API_KEY', ''),
        'base' => env('EXCHANGERATE_BASE', 'XOF'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Apple App Store Server API (In-App Purchase — abonnement auto-renouvelable)
    |--------------------------------------------------------------------------
    | Sert à vérifier les transactions StoreKit côté serveur et à traiter les
    | App Store Server Notifications v2. La clé privée (.p8) est générée dans
    | App Store Connect (Users and Access → Integrations → In-App Purchase).
    |   - sandbox=true en dev/TestFlight, false en production.
    |   - private_key : contenu PEM de la clé .p8 (ou chemin via APPLE_IAP_KEY_PATH).
    */
    'apple_iap' => [
        'bundle_id'   => env('APPLE_IAP_BUNDLE_ID', 'com.abbev.abbev'),
        'issuer_id'   => env('APPLE_IAP_ISSUER_ID'),
        'key_id'      => env('APPLE_IAP_KEY_ID'),
        'private_key' => env('APPLE_IAP_PRIVATE_KEY'),
        'key_path'    => env('APPLE_IAP_KEY_PATH'),
        'sandbox'     => (bool) env('APPLE_IAP_SANDBOX', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | ffmpeg (transcodage des vidéos locales vers MP4 H.264/AAC)
    |--------------------------------------------------------------------------
    | Les vidéos uploadées en .webm (ou autre) sont converties en .mp4 pour
    | une lecture universelle sur mobile (iOS/Android) et hors-ligne.
    | Requiert le binaire ffmpeg installé sur le serveur.
    */
    'ffmpeg' => [
        'bin'     => env('FFMPEG_BIN', 'ffmpeg'),
        'timeout' => (int) env('FFMPEG_TIMEOUT', 7200),
    ],

];
