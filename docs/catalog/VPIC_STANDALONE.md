# NHTSA vPIC standalone PostgreSQL database

## Purpose

NHTSA vPIC is the free official US VIN-decoding source used by the catalog. The standalone PostgreSQL database removes the API rate-limit dependency for VIN decoding while preserving the official HTTP API as a fallback.

NHTSA states that the standalone database is limited to VIN-decoding functionality. Makes, models, variables and other broader vPIC data still require the vPIC APIs or separate ingestion.

## Current official distribution

NHTSA publishes PostgreSQL 17+ backups from:

`https://vpic.nhtsa.dot.gov/Downloads`

As of 2026-09-06, the current standalone release is vPIC 4.08 / `vPICList_lite_2026_08`, updated 2026-08-14/15.

The installer does **not** hard-code that release. It discovers the newest official `vPICList_lite_YYYY_MM.custom.zip` link at runtime and records the exact release installed.

## Rights

NHTSA's Terms of Use state that information presented on its website is public information and may be distributed or copied. The catalog source is therefore configured for internal, ecommerce, derived and API use while retaining NHTSA attribution and the explicit prohibition on implying NHTSA endorsement.

## Requirements

- PostgreSQL 17 or newer
- PHP `ext-zip`
- `pg_restore` available to the application/CLI user
- the configured PostgreSQL user must be able to create/drop/rename schemas and restore the vPIC objects
- enough disk space for the ZIP plus extracted custom backup

The default Laravel connection is `pgsql` and the official standalone backup restores to schema `vpic`.

## Install/update

Seed catalog sources first, then run:

```bash
php artisan catalog:vpic:install
```

The command:

1. reads the official NHTSA downloads page;
2. discovers the newest PostgreSQL custom backup;
3. downloads it directly from `vpic.nhtsa.dot.gov`;
4. computes a ZIP checksum;
5. extracts exactly one expected `.custom` file without extracting arbitrary ZIP paths;
6. computes the custom-backup checksum;
7. validates it with `pg_restore --list`;
8. acquires a PostgreSQL advisory lock;
9. renames an existing `vpic` schema to `vpic_previous`;
10. restores the new official `vpic` schema;
11. verifies that `spVinDecode` exists;
12. drops the previous schema only after verification;
13. records the release/checksums in `catalog_source_releases`;
14. activates the VPIC source and switches resolver mode to `postgres_then_http`.

If restore or verification fails after the old schema has been renamed, the installer drops the partial new schema and renames `vpic_previous` back to `vpic`.

## Use an already downloaded archive

For restricted production networks or manual artifact management:

```bash
php artisan catalog:vpic:install --archive=/path/to/vPICList_lite_2026_08.custom.zip
```

Release discovery still runs against NHTSA so the local archive is associated with the current official release. Use only an archive obtained directly from NHTSA.

## Reinstall current release

```bash
php artisan catalog:vpic:install --force
```

By default, if `installed_release` already matches the newest official release, the command exits without touching the database.

## Preserve downloaded ZIP

```bash
php artisan catalog:vpic:install --keep-archive
```

Normally a newly downloaded ZIP and the extracted temporary `.custom` file are removed after successful installation. The installed backup checksum and original official URL remain in catalog source provenance.

## VIN resolution

After successful installation the source uses:

`postgres_then_http`

Normal lookup path:

1. validate 17-character VIN;
2. call `vpic.spVinDecode(...)` locally;
3. flatten vPIC variable/value rows;
4. match decoded make/model/year/engine data to the canonical vehicle graph;
5. if the local decoder is unavailable, fall back to the official HTTP vPIC resolver.

Results are cached for 24 hours by VIN and local database configuration.

## Updating

NHTSA has been publishing standalone DB refreshes roughly monthly. Run the installer periodically; it is release-aware and will no-op when the latest release is already installed.

Do not run overlapping installers. The command also uses a PostgreSQL advisory lock as a second line of protection.

## Scope limitation

vPIC represents vehicles intended for sale or importation into the United States. European-only vehicles can return limited data. The European vehicle backbone remains EEA type-approval / type-variant-version data plus manufacturer/supplier sources.

vPIC does **not** provide OEM parts, aftermarket cross-references or part fitments. It strengthens vehicle identification and VIN decoding only.
