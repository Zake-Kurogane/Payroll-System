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

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY', ''),
        'model'   => env('GEMINI_MODEL', 'gemini-2.0-flash'),
    ],

    'payslip_claims' => [
        'require_qr'                 => env('PAYSLIP_CLAIMS_REQUIRE_QR', false),
        'checkbox_confirm_cutoff'    => env('PAYSLIP_CLAIMS_CHECKBOX_CONFIRM_CUTOFF', 0.55),
        'checkbox_review_cutoff'     => env('PAYSLIP_CLAIMS_CHECKBOX_REVIEW_CUTOFF', 0.30),
        'no_qr_review_sig_cutoff'    => env('PAYSLIP_CLAIMS_NO_QR_REVIEW_SIG_CUTOFF', 0.04),
    ],

];
