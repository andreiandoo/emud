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
