# Catalog search projection

The public Catalog API must remain usable when canonical parts and fitments grow far beyond what ad-hoc `%ILIKE%` joins can serve efficiently. The first scalable backend is PostgreSQL-native and requires no external search service.

## Design

`catalog_search_documents` stores one denormalized document per searchable canonical entity:

- `catalog_part`
- `vehicle_configuration`

Part documents contain canonical names plus brand/category text and **only identifiers whose source allows API redistribution**. Vehicle documents contain make/model/generation, engine/year/market data, localized vehicle aliases, and only redistributable vehicle identifiers.

PostgreSQL gets a built-in GIN full-text index over `to_tsvector('simple', search_text)`. No extension is required. SQLite/test environments use a simple `LIKE` fallback.

The API search service uses the projection when documents exist and falls back to the pre-existing ORM search when the projection has not been built yet. Exact redistributable part-number lookup is ranked ahead of full-text results.

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

## Incremental maintenance

After the first full build, normal catalog changes maintain the projection automatically:

- canonical part create/update/delete/restore → one part document refresh;
- part-number create/update/delete → owning part refresh;
- vehicle-configuration create/update/delete → one vehicle document refresh;
- vehicle-identifier create/update/delete → owning vehicle refresh;
- vehicle alias create/update/delete → bounded refresh of only configurations under that make/model/generation;
- source `allow_api_redistribution` changes → phased refresh of parts, vehicle identifiers and aliases derived from that source.

Observer-triggered jobs are queued after the surrounding database transaction commits. This prevents rolled-back imports from creating search documents for data that never became canonical.

A full rebuild remains useful after bulk operational changes to parent names/categories or as an integrity repair operation.

## Rights boundary

Searchability is treated as redistribution. A restricted supplier/OE number must not become discoverable through the public API merely because the canonical part is otherwise visible. Projected part numbers, vehicle identifiers and vehicle aliases are therefore filtered by their source's `allow_api_redistribution` flag.

A rights flag change on a catalog source automatically reprojects documents that depend directly on that source, so revocation removes those terms without waiting for a scheduled full rebuild.

## Scale path

This projection is intentionally backend-neutral at the data-model level. If PostgreSQL full-text search eventually becomes the bottleneck, the same documents can be streamed to OpenSearch/Typesense without changing the canonical catalog or ingestion model. Fitment lookup remains in PostgreSQL using the existing vehicle/category/part indexes.
