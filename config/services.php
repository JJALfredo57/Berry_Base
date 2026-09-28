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

    'fcm' => [
        'project_id' => env('FCM_PROJECT_ID'),
        'credentials_path' => env('FCM_SERVICE_ACCOUNT_PATH'),
        'credentials_json' => env('FCM_SERVICE_ACCOUNT_JSON'),
        'credentials_json_b64' => env('FCM_SERVICE_ACCOUNT_JSON_B64'),
        'mobile_registration_enabled' => filter_var(env('FCM_MOBILE_REGISTRATION_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    ],

    'ocr' => [
        'driver' => env('OCR_DRIVER', 'auto'),
        'tesseract_binary' => env('OCR_TESSERACT_BINARY', 'tesseract'),
        'tesseract_lang' => env('OCR_TESSERACT_LANG', 'eng'),
        'tesseract_timeout' => (int) env('OCR_TESSERACT_TIMEOUT', 20),
        'service_url' => env('OCR_SERVICE_URL'),
        'service_health_url' => env('OCR_SERVICE_HEALTH_URL'),
        'service_token' => env('OCR_SERVICE_TOKEN'),
        'service_timeout' => (int) env('OCR_SERVICE_TIMEOUT', 30),
    ],

    'face_compare' => [
        'service_url' => env('FACE_COMPARE_SERVICE_URL'),
        'service_token' => env('FACE_COMPARE_SERVICE_TOKEN', env('OCR_SERVICE_TOKEN')),
        'timeout' => (int) env('FACE_COMPARE_SERVICE_TIMEOUT', env('OCR_SERVICE_TIMEOUT', 30)),
    ],

    'customer_identity' => [
        'provider' => env('CUSTOMER_ID_PROVIDER', 'current'),
        'staging_user_ids' => env('CUSTOMER_ID_STAGING_USER_IDS', ''),
    ],

    'openbiometrics' => [
        'base_url' => env('OPENBIOMETRICS_BASE_URL'),
        'api_key' => env('OPENBIOMETRICS_API_KEY'),
        'timeout' => (int) env('OPENBIOMETRICS_TIMEOUT', 30),
        'face_threshold' => (float) env('OPENBIOMETRICS_FACE_THRESHOLD', 0.4),
    ],
];
