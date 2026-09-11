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

    'holiday_api' => [
        'base_url' => env('HOLIDAY_API_BASE_URL', 'https://use.api.co.id'),
        'token' => env('HOLIDAY_API_TOKEN'),
    ],

    'evidence_ai' => [
        'enabled' => env('AI_EVIDENCE_ENABLED', true),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'evidence_model' => env('OPENAI_EVIDENCE_MODEL', 'gpt-5-nano'),
    ],

];
