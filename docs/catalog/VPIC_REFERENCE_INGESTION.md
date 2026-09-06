# NHTSA vPIC reference ingestion

The vPIC integration has two independent layers:

1. `catalog:vpic:install` installs the official standalone PostgreSQL database used for local VIN decoding.
2. `VpicReferenceCatalogSourceConnector` ingests the broader reference data that NHTSA exposes only through the rate-controlled vPIC API.

NHTSA states that the standalone database is limited to VIN decoding; makes, models, variables, attributes and manufacturer/reference information still require API access.

## Canonical graph

The vPIC reference importer populates:

- `vehicle_manufacturers`
- `vehicle_makes`
- `vehicle_models`
- `vehicle_make_manufacturers`
- `vehicle_entity_identifiers`
- `vehicle_make_types`
- `vehicle_model_years`
- `vehicle_wmis`

The canonical source identifiers are retained as first-class identifiers:

- `vpic_manufacturer_id`
- `vpic_make_id`
- `vpic_model_id`
- `wmi`

These identifiers are intended to become reconciliation anchors for EEA, lifeofcapo, Wikidata, supplier and future OEM/IAM feeds.

## Modes

### `catalog`

Fetches the low-request global reference sets:

- all manufacturers, using NHTSA's 100-row pagination
- all makes
- all models using `GetModelsForMakeId/0`

Run this first so later relationship modes can resolve vPIC IDs to canonical entities.

```bash
php artisan catalog:sources:sync VPIC --mode=catalog
```

### `manufacturer_links`

For a configurable batch of manufacturers per run, fetches:

- manufacturer -> make relationships
- all WMI records for the manufacturer

Default batch size: 25 manufacturers.

```bash
php artisan catalog:sources:sync VPIC --mode=manufacturer_links
```

### `vehicle_types`

For a configurable batch of makes per run, fetches the NHTSA vehicle types attached to each make.

Default batch size: 50 makes.

```bash
php artisan catalog:sources:sync VPIC --mode=vehicle_types
```

### `model_years`

Builds explicit model-year membership using `GetModelsForMakeIdYear`. NHTSA documents this API for model years greater than 1995, so the default lower bound is 1996.

This is deliberately the slowest mode because its request count is approximately:

`number of makes x number of model years`

Default batch size is only 5 makes per run. The upper year should be left unset to use current year + 1, or explicitly configured for reproducible backfills.

```bash
php artisan catalog:sources:sync VPIC --mode=model_years
```

## Resumability and rate control

The expensive modes persist their cursor in:

```text
catalog_sources.settings.reference_checkpoints
```

A successful batch does **not** mean the entire source has been exhausted. Schedule the long modes repeatedly; each run resumes from the stored cursor. When the whole parent set has been traversed, the corresponding checkpoint is removed.

Relevant settings:

```json
{
  "reference_request_interval_ms": 250,
  "manufacturer_link_batch_size": 25,
  "vehicle_type_batch_size": 50,
  "model_year_make_batch_size": 5,
  "model_year_from": 1996
}
```

Increase request spacing if NHTSA traffic control starts returning throttling responses. Do not work around NHTSA traffic controls with parallel request floods.

## Recommended acquisition sequence

1. Install/refresh the standalone vPIC PostgreSQL VIN decoder.
2. Run `catalog` and allow canonicalization to finish.
3. Repeatedly run `manufacturer_links` until its checkpoint disappears.
4. Repeatedly run `vehicle_types` until its checkpoint disappears.
5. Run `model_years` as a long-running backfill, beginning with the years relevant to the store's active vehicle parc if infrastructure capacity is limited.
6. Refresh the cheap `catalog` mode periodically; run relationship modes incrementally afterward.

## Coverage limitation

vPIC represents vehicles intended for sale or importation into the United States. It is an authoritative free US backbone, not a replacement for European homologation data. European coverage continues to come from the EEA ingestion and later manufacturer/supplier sources.

## Provenance

Every API row is first stored in `catalog_source_records` with a checksum and import-run linkage. Canonicalization then creates source assertions and vPIC entity identifiers. This preserves the ability to rebuild canonical entities or audit a relationship back to the raw NHTSA response.
