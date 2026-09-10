<?php

return [
    'catalog' => [
        'default_currency' => env('STORE_CURRENCY', 'RON'),
        'default_vat_rate' => (float) env('STORE_VAT_RATE', 21),
        // What the shop can be priced in. Free text let a typo through and there is no way to
        // notice one: the wrong code just quietly formats every price with the wrong symbol.
        'currencies' => [
            'RON' => 'Leu românesc (RON)',
            'EUR' => 'Euro (EUR)',
            'USD' => 'Dolar american (USD)',
            'GBP' => 'Liră sterlină (GBP)',
            'BGN' => 'Levă bulgărească (BGN)',
            'HUF' => 'Forint maghiar (HUF)',
            'PLN' => 'Zlot polonez (PLN)',
        ],
    ],
    'pricing' => [
        'default_markup_percent' => (float) env('DEFAULT_MARKUP_PERCENT', 25),
        'price_ending' => (float) env('PRICE_ENDING', 0.99),

        // National Bank of Romania reference rates. RON-denominated, which suits a
        // RON base currency; verify the document shape before trusting an import.
        // The National Bank of Romania withdrew nbrfxrates.xml — every known path now redirects
        // to its homepage, so the fetch returned HTML and the parse failed. The European Central
        // Bank publishes the same reference rates daily at a stable address, and the importer
        // cross-multiplies them into the store's currency.
        'exchange_rates_url' => env('EXCHANGE_RATES_URL', 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml'),

        // Inputs to contribution margin. Planning defaults until real processor and
        // returns data exist; they are configuration precisely so they can be
        // replaced with measured values rather than re-derived in code.
        'margins' => [
            'payment_fee_percent' => (float) env('PAYMENT_FEE_PERCENT', 1.6),
            'payment_fee_fixed' => (float) env('PAYMENT_FEE_FIXED', 0),
            'return_rate_percent' => (float) env('RETURN_RATE_PERCENT', 4),
            'return_handling_cost' => (float) env('RETURN_HANDLING_COST', 15),
            'bulky_return_multiplier' => (float) env('BULKY_RETURN_MULTIPLIER', 2.5),
            'warranty_reserve_percent' => (float) env('WARRANTY_RESERVE_PERCENT', 1),
        ],
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

        // How much more the shop will pay to have a part sooner. Routing compares suppliers
        // on landed cost raised by these penalties, so each one reads as a willingness to
        // pay: a backordered offer must be more than 8% cheaper to beat one in stock.
        'routing' => [
            'backorder_penalty_percent' => (float) env('ROUTING_BACKORDER_PENALTY', 8),
            'low_stock_penalty_percent' => (float) env('ROUTING_LOW_STOCK_PENALTY', 2),
            'dispatch_day_penalty_percent' => (float) env('ROUTING_DISPATCH_DAY_PENALTY', 0.5),
            // An offer that states no dispatch window is assumed slow, not instant.
            'unknown_dispatch_days' => (int) env('ROUTING_UNKNOWN_DISPATCH_DAYS', 5),
        ],
    ],
];
