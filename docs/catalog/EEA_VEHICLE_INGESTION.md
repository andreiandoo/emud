# EEA European vehicle ingestion

## Purpose

The European Environment Agency (EEA) CO2 monitoring datasets provide an official, free European vehicle-registration backbone for the automotive catalog. They are not a parts-fitment source, but they provide identifiers that are extremely valuable for joining later manufacturer, supplier and homologation data:

- make (`Mk`)
- commercial name (`Cn`)
- EU type-approval number (`Tan`)
- type (`T`)
- variant (`Va`)
- version (`Ve`)
- type-approved and registered vehicle categories (`Ct`, `Cr`)
- fuel type/mode (`Ft`, `Fm`)
- engine capacity (`ec`)
- maximum net power (`ep`)
- model/registration year
- additional technical values such as mass, WLTP/NEDC CO2 and electric consumption

The canonical identifier chain for European matching should therefore prefer:

`EU type approval + type + variant + version + year + engine characteristics`

rather than relying only on free-text make/model names.

## Current release

The seeded source is pinned to the EEA provisional 2025 tables published 2026-06-25:

- passenger cars: `[CO2Emission].[latest].[co2cars_2025Pv31]`
- vans: `[CO2Emission].[latest].[co2vans_2025Pv27]`

Pinning explicit versioned table names makes an import reproducible even when EEA later changes the `latest` aggregate views.

## Access

EEA Discodata exposes a public SQL-over-HTTP endpoint:

`https://discodata.eea.europa.eu/sql`

The connector sends a T-SQL query in the `query` parameter and paginates using `p` and `nrOfHits`. Results are JSON under `results`.

No authentication is required.

## Rights

The source is configured as `open_redistributable` with attribution required. The EEA legal notice states that EEA-held data/content can generally be reused commercially and non-commercially with EEA acknowledgement, while dataset-specific third-party rights must still be respected.

Before adding a new EEA dataset family, verify its own metadata/licensing and do not assume the generic EEA terms override a dataset-specific notice.

## Import modes

```bash
php artisan catalog:sources:sync EEA --mode=cars
php artisan catalog:sources:sync EEA --mode=vans
php artisan catalog:sources:sync EEA --mode=vehicles
php artisan catalog:sources:sync EEA --mode=catalog
```

`catalog` and `vehicles` import all configured EEA vehicle datasets. `cars` and `vans` restrict the import to one vehicle family.

## Why the connector uses DISTINCT configurations

Raw CO2 monitoring datasets describe registrations. Multiple rows can describe the same technical vehicle configuration because registrations differ by country, date or reporting record.

For the parts catalog, importing every registration row would create enormous redundancy without adding fitment value. The connector therefore asks Discodata for distinct combinations of:

- make/commercial name
- type approval
- type/variant/version
- categories
- fuel type/mode
- engine capacity/power
- selected technical properties
- year

This still preserves source evidence in `catalog_source_records`, while dramatically reducing the number of rows sent through canonicalization.

## Provenance

Every emitted record includes:

- `external_id`: deterministic hash of the technical configuration and pinned source table
- `source_vehicle_kind`: `cars` or `vans`
- `source_table`: exact EEA versioned table
- `source_status`: provisional/final marker from source configuration
- `year` and `registration_year`

The import release records the full dataset table list and EEA publication date.

## Canonicalization

`EeaVehicleCanonicalizer` creates/updates:

- `vehicle_makes`
- `vehicle_models`
- `vehicle_generations`
- `vehicle_engines`
- `vehicle_configurations`
- source assertions

Important: EEA field `r` means **total new registrations**. It is not a year field. Registration year must come from `year` / `registration_year`.

Additional technical values are retained in vehicle-configuration metadata and assertions even when there is no dedicated canonical column yet.

## Historical expansion

The connector accepts a `settings.datasets` array, so historical EEA snapshots can be added without code changes. Prefer explicit versioned/final tables for each year where possible.

Recommended expansion order:

1. current 2025 provisional cars + vans
2. 2024 final cars + vans
3. 2023 final cars + vans
4. continue backwards through passenger cars (2010+) and vans (2012+)

When a final table supersedes a provisional table, add it as a new pinned source release rather than silently rewriting provenance.

## Limitations

EEA does **not** provide:

- OEM part numbers
- aftermarket numbers
- vehicle-to-part fitment
- supersessions
- option/PR-code-level equipment sufficient for every exact fitment

Its role is the European vehicle identity/homologation backbone. Parts data must still come from manufacturer/supplier feeds and other permitted sources.

## Scale, resume and per-dataset columns

### The import is far longer than one job window

The 2025 provisional passenger-car table yields **1,332,309 distinct configuration rows** at the projection this connector selects, measured against Discodata. At an observed ~66 rows/second that is roughly five and a half hours, against a `SyncCatalogSource` timeout of 7200 seconds.

`EeaVehicleCatalogSourceConnector` therefore implements `ResumableCatalogSourceConnector`. The current dataset index and page are written to `catalog_import_runs.checkpoint` every 500 records and whenever a run fails, and the next attempt continues from there instead of restarting at page one. `SyncCatalogSource` uses that budget deliberately: `tries = 6`, with `maxExceptions = 3` so a genuinely broken connector still fails fast rather than consuming every attempt.

The retry backoff is 7800 seconds, deliberately longer than the `WithoutOverlapping` lock's `expireAfter(7500)`. A job killed by its timeout never releases that lock, so a retry scheduled sooner would find it held and be discarded silently instead of rescheduled.

A checkpoint records a fingerprint of the dataset list it was taken against. Changing the configured datasets or moving to a new release invalidates it, so a resumed import can never continue into the middle of a feed it never started.

### Fetched rows greatly exceed staged records

Record identity hashes twelve fields and deliberately excludes mass and the CO2 measurements, while the `SELECT DISTINCT` projection includes them. Those near-continuous values multiply the distinct row count roughly tenfold, so expect around ten fetched rows per staged record. Both numbers are correct: `fetched` counts rows pulled from Discodata, `staged records` counts canonical configurations.

Widening the identity hash would reduce the fetch volume, but it would also split one configuration into many records that differ only by measured mass or CO2. Do not narrow the projection to make the numbers match.

### Datasets do not share a column set

The 2025 vans table has no `Mt` (test mass) column while the cars table does, and Discodata rejects the whole query with `Invalid column name 'Mt'` rather than ignoring it — as HTTP 200 with an `errors` key, not an error status.

The connector therefore probes each table before building its query: one combined `SELECT TOP 1` in the normal case, falling back to per-column probes to identify exactly which columns are absent. Missing optional columns are dropped from the projection; missing `Mk`, `Cn` or `year` is a hard error, because identity depends on them. A dataset may also declare `exclude_columns` in its settings to skip a column without probing.

`testConnection` runs the real projection rather than a two-column sample, so a schema mismatch surfaces in the admin connection test instead of hours into an import.
