<?php

return [
    'drivers' => [
        'paywuz' => [
            'enabled' => (bool) env('PAYWUZ_ENABLED', false),
            'api_key' => env('PAYWUZ_API_KEY'),
            'base_url' => env('PAYWUZ_BASE_URL', 'https://api.paywuz.id/v1'),
            'connect_timeout_seconds' => (int) env('PAYWUZ_CONNECT_TIMEOUT_SECONDS', 5),
            'timeout_seconds' => (int) env('PAYWUZ_TIMEOUT_SECONDS', 15),
            'method_cache_seconds' => (int) env('PAYWUZ_METHOD_CACHE_SECONDS', 300),
        ],
    ],
];
