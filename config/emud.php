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

        // Cap on error rows stored per run. A supplier that renames a column produces
        // one error per line; the pattern is clear long before the millionth row.
        'max_errors_per_run' => (int) env('SUPPLIER_MAX_ERRORS_PER_RUN', 500),

        // A product absent from the catalogue feed for this long is retired, never
        // deleted. Suppliers routinely drop and restore articles within a few days.
        'discontinue_after_days' => (int) env('SUPPLIER_DISCONTINUE_AFTER_DAYS', 7),

        // Protects against a truncated feed being mistaken for the full catalogue.
        // Overridable per supplier through settings.volume_guard.
        'volume_guard' => [
            'enabled' => (bool) env('SUPPLIER_VOLUME_GUARD', true),
            'minimum_baseline_records' => (int) env('SUPPLIER_VOLUME_GUARD_BASELINE', 100),
            'minimum_ratio' => (float) env('SUPPLIER_VOLUME_GUARD_RATIO', 0.7),
        ],

        // How old a successful sync may get before the supplier is reported unhealthy.
        'health' => [
            'catalog_stale_after_hours' => (int) env('SUPPLIER_CATALOG_STALE_HOURS', 48),
            'prices_stale_after_hours' => (int) env('SUPPLIER_PRICES_STALE_HOURS', 12),
            'stock_stale_after_hours' => (int) env('SUPPLIER_STOCK_STALE_HOURS', 6),
            'error_rate_threshold' => (float) env('SUPPLIER_ERROR_RATE_THRESHOLD', 0.05),
        ],
    ],
];
