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
];
