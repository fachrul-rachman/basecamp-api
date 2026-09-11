<?php

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    // Comma-separated list of allowed frontend origins in production
    // (e.g. "https://app.example.com,https://admin.example.com"). Defaults
    // to "*" for local development. This API uses Bearer tokens (Sanctum
    // personal access tokens), not cookies, so `supports_credentials`
    // stays false and a wildcard origin here carries no CSRF/session risk.
    'allowed_origins' => array_filter(explode(',', (string) env('CORS_ALLOWED_ORIGINS', '*'))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
