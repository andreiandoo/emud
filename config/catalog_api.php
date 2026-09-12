<?php

return [
    'rapidapi' => [
        'proxy_secret' => env('RAPIDAPI_PROXY_SECRET'),
        'expected_host' => env('RAPIDAPI_EXPECTED_HOST'),
        'auto_provision' => env('RAPIDAPI_AUTO_PROVISION', true),
        'plan_quotas' => [
            'BASIC' => (int) env('RAPIDAPI_BASIC_LOCAL_QUOTA', 0),
            'PRO' => (int) env('RAPIDAPI_PRO_LOCAL_QUOTA', 0),
            'ULTRA' => (int) env('RAPIDAPI_ULTRA_LOCAL_QUOTA', 0),
            'MEGA' => (int) env('RAPIDAPI_MEGA_LOCAL_QUOTA', 0),
            'CUSTOM' => (int) env('RAPIDAPI_CUSTOM_LOCAL_QUOTA', 0),
        ],
    ],

    'cache' => [
        'enabled' => env('CATALOG_API_CACHE_ENABLED', true),
        // Short, because the catalog version stamp already retires entries the moment anything
        // publishable changes. The TTL is only a backstop for a stamp that never arrives.
        'ttl' => (int) env('CATALOG_API_CACHE_TTL', 300),
    ],

    'batch' => [
        // One batch request costs one metered request, so the ceiling is what stops a caller
        // from draining the catalogue through a handful of calls.
        'max_items' => (int) env('CATALOG_API_BATCH_MAX_ITEMS', 50),
    ],
];
