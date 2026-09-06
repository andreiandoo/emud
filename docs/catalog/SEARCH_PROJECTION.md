# Catalog search projection

The public Catalog API must remain usable when canonical parts and fitments grow far beyond what ad-hoc `%ILIKE%` joins can serve efficiently. The first scalable backend is PostgreSQL-native and requires no external search service.

## Design

`catalog_search_documents` stores one denormalized document per searchable canonical entity:

- `catalog_part`
- `vehicle_configuration`

Part documents contain canonical names/MPNs, brand/category text and **only identifiers whose source allows API redistribution**. Vehicle documents contain make/model/generation, engine/year/market data, localized vehicle aliases, and only redistributable vehicle identifiers.

PostgreSQL gets a built-in GIN full-text index over `to_tsvector('simple', search_text)`. No extension is required. SQLite/test environments use a simple `LIKE` fallback.

The API search service uses the projection when documents exist and falls back to the pre-existing ORM search when the projection has not been built yet. Exact normalized part-number lookup is ranked ahead of full-text results.

## Build/rebuild

After migrations:

```bash
php artisan catalog:search:rebuild --reset
```

For a very large database, use bounded batches processed by the `catalog-search` queue:

```bash
php artisan catalog:search:rebuild --entity=catalog_part --chunk=2000
php artisan catalog:search:rebuild --entity=vehicle_configuration --chunk=2000
```

Each queue job projects only one ordered ID range and dispatches the next range. Jobs are idempotent because documents are upserted by `(entity_type, entity_id)`.

Use `--reset` for a clean full rebuild after large imports or deletions. Without `--reset`, rebuilds update existing documents in place.

## Rights boundary

Searchability is treated as redistribution. A restricted supplier/OE number must not become discoverable through the public API merely because the canonical part is otherwise visible. Projected part numbers and vehicle identifiers are therefore filtered by their source's `allow_api_redistribution` flag.

## Scale path

This projection is intentionally backend-neutral at the data-model level. If PostgreSQL full-text search eventually becomes the bottleneck, the same documents can be streamed to OpenSearch/Typesense without changing the canonical catalog or ingestion model. Fitment lookup remains in PostgreSQL using the existing vehicle/category/part indexes.
