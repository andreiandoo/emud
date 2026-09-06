# Open-source catalog sources

This document describes the sources that can be synchronized without copying a proprietary catalog.

## lifeofcapo/car-api (MIT)

The connector imports directly from the repository JSON files rather than the npm/browser bundle:

- `car-brands.json`: brand → model → generation (`name`, `yearFrom`, `yearTo`)
- `car-parts.json`: generic `{slug, name}` taxonomy only

The connector first resolves the configured upstream Git ref through the GitHub commits API, stores that exact commit SHA in `catalog_source_releases`, and downloads both raw JSON files pinned to that SHA. This makes a sync reproducible even if `main` changes during or after the import.

The generic parts stream is deliberately not converted into `catalog_parts`: it contains names/taxonomy but no MPN, OEM number or vehicle fitment evidence. It remains staged as `generic_part_taxonomy` source data for future category/alias mapping.

### Enable and import

Seed/update the source definition, then enable `LIFEOFCAPO` in **Admin → Surse catalog**.

```bash
php artisan db:seed --class=CatalogSourceSeeder
php artisan catalog:sources:sync LIFEOFCAPO --mode=catalog
```

`catalog` imports vehicle generations plus the generic parts taxonomy. To limit the source stream:

```bash
php artisan catalog:sources:sync LIFEOFCAPO --mode=vehicles
php artisan catalog:sources:sync LIFEOFCAPO --mode=generic_parts
```

With `auto_canonicalize=true`, vehicle-generation records are canonicalized into `vehicle_makes`, `vehicle_models`, and `vehicle_generations`; generic taxonomy records are explicitly retained as source-only evidence and skipped as technical parts.

### Pinning a specific upstream commit

For deterministic re-imports, set this in source settings:

```json
{
  "upstream_commit": "<40-character git sha>"
}
```

When present, the connector uses that commit directly and does not resolve `main` through the GitHub API.

## Wikidata

Wikidata is configured as CC0 enrichment, not as authoritative parts-fitment truth. The public Wikidata Query Service has strict compute, concurrency, error-rate and User-Agent rules. The planned adapter therefore uses chunked queries, an identifiable User-Agent, caching, `Retry-After` backoff and resumable checkpoints rather than treating WDQS as a live search dependency.
