<?php

/*
 * The national workshop registry: RAR authorisations, ONRC legal identity, OpenStreetMap
 * locations and what workshops say about themselves on their own websites.
 *
 * Every remote call is paced from here. The defaults are deliberately slow: the registry is
 * refreshed weekly, and nothing about it is urgent enough to lean on public infrastructure.
 */
return [
    // One queue for the whole subsystem, so a slow national import never sits in front of the
    // shop's own catalogue jobs. Listed in CatalogSystemCheck::PIPELINE_QUEUES.
    'queue' => 'workshops',

    // Sent with every request, so an operator on the other end can see who is calling and why.
    'user_agent' => env('WORKSHOPS_USER_AGENT', 'eMUD-WorkshopRegistry/1.0 (+https://a-n.ro/contact)'),

    'rar' => [
        'enabled' => (bool) env('RAR_IMPORT_ENABLED', true),

        // The public registry behind https://portal.rarom.ro/rar-public/registry-search. It is an
        // Angular application; these are the same public endpoints its own pages call.
        'base_url' => env('RAR_API_BASE_URL', 'https://portal.rarom.ro/rarApi'),
        // The activity nomenclature (A1.2.1.1 and its wording) ships as the portal's translation
        // file. A bundled copy is the fallback when it cannot be fetched.
        'nomenclature_url' => env('RAR_NOMENCLATURE_URL', 'https://portal.rarom.ro/rar-public/assets/i18n/ro.json'),

        'request_delay_ms' => (int) env('RAR_REQUEST_DELAY_MS', 2000),
        'request_timeout' => (int) env('RAR_REQUEST_TIMEOUT', 90),
        'max_retries' => (int) env('RAR_MAX_RETRIES', 4),
        // First pause after a failed request; each retry doubles it.
        'retry_base_delay_ms' => (int) env('RAR_RETRY_BASE_DELAY_MS', 3000),
        // The portal's own page offers 2, 5, 200 and 400 rows. Larger pages mean fewer requests;
        // anything above the portal's own ceiling is asking for more than it was built to serve.
        'page_size' => (int) env('RAR_PAGE_SIZE', 250),

        // Registry sections, each its own data source: an ITP station is not a repair shop and
        // a GPL fitter is not an ITP station, even when all three sit at the same address.
        'sections' => [
            'SERVICE' => ['source' => 'rar_service', 'name' => 'RAR – ateliere service auto'],
            'ITP' => ['source' => 'rar_itp', 'name' => 'RAR – stații ITP'],
            'GPL' => ['source' => 'rar_gpl', 'name' => 'RAR – montaj și revizie GPL/GNC'],
            'TLV' => ['source' => 'rar_tlv', 'name' => 'RAR – tahografe și limitatoare de viteză'],
            'B4' => ['source' => 'rar_modifications', 'name' => 'RAR – modificări, conversii și reconstrucții'],
        ],

        // A county that suddenly returns far fewer authorisations than it held is far more likely
        // to be a truncated response than a mass withdrawal. Below this share of the previous
        // count, nothing in that county is retired.
        'retire_guard_ratio' => (float) env('RAR_RETIRE_GUARD_RATIO', 0.6),
    ],

    'onrc' => [
        // data.gov.ro is CKAN; the newest "Firme înregistrate…" package is found through its API
        // rather than a resource id pinned here, because every quarterly release gets new ids.
        'ckan_url' => env('ONRC_CKAN_URL', 'https://data.gov.ro/api/3/action'),
        'organization' => env('ONRC_CKAN_ORGANIZATION', 'onrc'),
        'dataset_prefix' => 'firme-',
        'nomenclature_prefix' => 'nomenclatoare-',
        'request_timeout' => (int) env('ONRC_REQUEST_TIMEOUT', 1800),

        // Vehicle repair under both CAEN revisions: 4520 until the 2025 revision, 9531 after it.
        // ONRC lists every authorised activity, not only the main one, and all of them count.
        'caen_codes' => [
            ['code' => '4520', 'version' => '2', 'label' => 'Întreținerea și repararea autovehiculelor (CAEN Rev. 2)'],
            ['code' => '9531', 'version' => '3', 'label' => 'Repararea și întreținerea autovehiculelor (CAEN Rev. 3)'],
        ],

        // The files are large (the company file alone is ~700 MB). They are kept only until the
        // import finishes unless told otherwise.
        'keep_files' => (bool) env('ONRC_KEEP_FILES', false),
        'disk' => env('WORKSHOPS_DISK', 'local'),
        'directory' => 'workshops/onrc',
    ],

    'osm' => [
        'extract_url' => env('OSM_EXTRACT_URL', 'https://download.geofabrik.de/europe/romania-latest.osm.pbf'),
        'osmium_binary' => env('OSMIUM_BINARY', 'osmium'),
        'disk' => env('WORKSHOPS_DISK', 'local'),
        'directory' => 'workshops/osm',
        'request_timeout' => (int) env('OSM_REQUEST_TIMEOUT', 1800),

        // What osmium keeps. Car repair is the core; tyre shops, truck repair and inspection
        // stations are workshops too, and a dealer only when it says it services vehicles.
        'tag_filters' => [
            'nwr/shop=car_repair',
            'nwr/shop=tyres',
            'nwr/shop=truck_repair',
            'nwr/craft=car_repair',
            'nwr/amenity=vehicle_inspection',
            'nwr/service:vehicle:repairs=yes',
        ],

        // Two points closer than this, with names that agree, are the same workshop.
        'match_distance_meters' => (int) env('OSM_MATCH_DISTANCE', 200),
    ],

    'geocoder' => [
        // null | nominatim. Without a configured geocoder addresses stay pending; nothing is
        // guessed.
        'driver' => env('WORKSHOPS_GEOCODER', 'null'),
        'nominatim_url' => env('WORKSHOPS_NOMINATIM_URL'),
        // The public nominatim.openstreetmap.org forbids bulk geocoding. It is refused unless
        // this is switched on for a small, supervised batch.
        'allow_public_nominatim' => (bool) env('WORKSHOPS_ALLOW_PUBLIC_NOMINATIM', false),
        'request_delay_ms' => (int) env('WORKSHOPS_GEOCODER_DELAY_MS', 1100),
        'max_per_run' => (int) env('WORKSHOPS_GEOCODER_MAX_PER_RUN', 500),
    ],

    'web' => [
        // null | brave. Search enrichment is skipped cleanly without a key.
        'search_provider' => env('WORKSHOPS_SEARCH_PROVIDER', 'null'),
        'brave_api_key' => env('BRAVE_SEARCH_API_KEY'),
        'brave_endpoint' => env('BRAVE_SEARCH_ENDPOINT', 'https://api.search.brave.com/res/v1/web/search'),
        'search_delay_ms' => (int) env('WORKSHOPS_SEARCH_DELAY_MS', 1100),

        'max_pages_per_site' => (int) env('WORKSHOPS_CRAWL_MAX_PAGES', 8),
        'max_response_bytes' => (int) env('WORKSHOPS_CRAWL_MAX_BYTES', 1_500_000),
        'request_timeout' => (int) env('WORKSHOPS_CRAWL_TIMEOUT', 20),
        'request_delay_ms' => (int) env('WORKSHOPS_CRAWL_DELAY_MS', 1500),
        // Websites are refreshed rarely: what a workshop says about itself changes slowly.
        'recrawl_after_days' => (int) env('WORKSHOPS_RECRAWL_AFTER_DAYS', 90),

        // Listing sites, marketplaces and social networks are never a workshop's own website.
        // Social profiles are kept, but as contacts of their own type.
        'directory_domains' => [
            'listafirme.ro', 'termene.ro', 'risco.ro', 'firme.info', 'infocui.ro', 'confidas.ro',
            'totalfirme.ro', 'firmepenet.ro', 'romanian-companies.eu', 'cylex.ro', 'cylex-romania.ro',
            'paginiaurii.ro', 'infobel.com', '2gis.ro', 'yelp.com', 'tripadvisor.com', 'tripadvisor.ro',
            'olx.ro', 'autovit.ro', 'publi24.ro', 'lajumate.ro', 'storia.ro', 'google.com', 'google.ro',
            'goo.gl', 'maps.app.goo.gl', 'waze.com', 'bing.com', 'wikipedia.org', 'rarom.ro', 'onrc.ro',
            'data.gov.ro', 'openstreetmap.org', 'facebook.com', 'fb.com', 'instagram.com', 'tiktok.com',
            'youtube.com', 'linkedin.com', 'twitter.com', 'x.com', 'wa.me', 'whatsapp.com',
            'service-auto.ro', 'autoservice.ro', 'ghidulfirmelor.ro', 'firmeromania.ro', 'topfirme.com',
            'emag.ro', 'autodoc.ro', 'epiesa.ro', 'a-n.ro',
        ],
    ],

    // Nothing here runs on its own until switched on: a national refresh is a decision, not a
    // default. Website crawling is never scheduled; it is started by hand, in bounded batches.
    'schedule' => [
        'enabled' => (bool) env('WORKSHOPS_SCHEDULE_ENABLED', false),
        'rar_cron' => env('WORKSHOPS_RAR_CRON', '30 4 * * 0'),
        'osm_cron' => env('WORKSHOPS_OSM_CRON', '0 5 3 * *'),
        'onrc_cron' => env('WORKSHOPS_ONRC_CRON', '0 6 10 * *'),
    ],
];
