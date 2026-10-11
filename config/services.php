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

    'twilio' => [
        'sid' => env('TWILIO_ACCOUNT_SID'),
        'token' => env('TWILIO_AUTH_TOKEN'),
        'whatsapp_from' => env('TWILIO_WHATSAPP_FROM'),
        'messaging_service_sid' => env('TWILIO_MESSAGING_SERVICE_SID'),

        // Ruta que se registra como statusCallback en cada mensaje enviado.
        // Debe coincidir con la configurada en la consola de Twilio.
        'status_callback_path' => env('TWILIO_STATUS_CALLBACK_PATH', '/api/twilio/whatsapp/status'),

        // Solo desactívalo en local: sin firma cualquiera puede inyectar
        // respuestas falsas en los webhooks.
        'verify_webhook_signature' => (bool) env('TWILIO_VERIFY_WEBHOOK_SIGNATURE', true),
    ],

    'wppconnect' => [
        'base_url' => env('WPPCONNECT_BASE_URL'),
        'token' => env('WPPCONNECT_TOKEN'),
        'default_session' => env('WPPCONNECT_DEFAULT_SESSION', 'default'),
        'delay_min_seconds' => (int) env('WPPCONNECT_DELAY_MIN_SECONDS', 10),
        'delay_max_seconds' => (int) env('WPPCONNECT_DELAY_MAX_SECONDS', 25),
        'campaign_image_url' => env('WPPCONNECT_CAMPAIGN_IMAGE_URL'),

        // Token que deben mandar los clientes de POST /api/wpp/campaigns/send
        // (Authorization: Bearer …). Vacío = endpoint cerrado.
        'api_token' => env('WPPCONNECT_API_TOKEN'),
    ],

    'retell' => [
        'secret' => env('RETELL_WEBHOOK_SECRET'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'whatsapp_worker' => [
        'token' => env('WHATSAPP_WORKER_TOKEN'),
        'reserve_seconds' => (int) env('WHATSAPP_RESERVE_SECONDS', 180),
        'max_attempts' => (int) env('WHATSAPP_MAX_ATTEMPTS', 5),
    ],

];
