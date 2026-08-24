<?php

return [
    'catalog' => [
        'default_currency' => env('STORE_CURRENCY', 'RON'),
        'default_vat_rate' => (float) env('STORE_VAT_RATE', 21),
    ],
    'pricing' => [
        'default_markup_percent' => (float) env('DEFAULT_MARKUP_PERCENT', 25),
        'price_ending' => (float) env('PRICE_ENDING', 0.99),
    ],
    'suppliers' => [
        'default_stale_after_minutes' => (int) env('SUPPLIER_STALE_AFTER_MINUTES', 60),
    ],
];
