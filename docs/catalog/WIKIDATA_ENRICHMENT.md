# Wikidata vehicle enrichment

Wikidata is used as a background enrichment source for canonical vehicle makes/models and, only when explicitly requested, generations. It is not a fitment authority and is never used to infer part compatibility.

## Why this is a background job

The public Wikimedia APIs require identifiable clients and considerate usage. The implementation uses the MediaWiki Action API, sends a descriptive User-Agent, sets `maxlag`, makes serial requests, persists results locally, and honors `429`/`Retry-After` by releasing the queue job. No storefront or Catalog API request depends on Wikidata availability.

## Source configuration

Enable the `WIKIDATA` source after reviewing its rights configuration. Recommended settings:

- `action_api_url`: `https://www.wikidata.org/w/api.php`
- `user_agent`: an identifiable project/version plus a contact URL or email
- `maxlag`: `5`
- `search_limit`: `5`
- `search_language`: `en`
- `languages`: `en`, `ro`, `de`, `fr`, `it`, `es`

The source is CC0 for Wikidata structured data. Preserve the source/QID assertions even though attribution is not legally required by CC0; provenance is operationally valuable.

## Run

```bash
php artisan catalog:wikidata:enrich
```

Default entity types are `vehicle_make,vehicle_model`. Generations are opt-in because Wikidata generation-level coverage/naming is inconsistent:

```bash
php artisan catalog:wikidata:enrich --types=vehicle_make,vehicle_model,vehicle_generation --batch=20
```

Each command creates a `catalog_import_runs` row. The queue job stores `type_index` and `last_id` in the run checkpoint after every processed canonical entity. Work is split into bounded jobs, so enrichment is resumable and safe to operate on a large catalog.

## Published data

A high-confidence match writes:

- the selected Wikidata QID, label and description as source assertions;
- localized Wikidata labels/aliases into `vehicle_aliases`;
- the full search/result evidence into `catalog_source_records`.

Weak or closely competing candidates remain `skipped` or `ambiguous`; they are retained as evidence but do not modify aliases. Fitment, OEM numbers and compatibility are never derived from Wikidata aliases.
