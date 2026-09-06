# Automotive Catalog Platform

This directory is the implementation specification for evolving eMUD from an ecommerce catalog into a reusable automotive technical catalog, fitment graph and API backbone.

## Architectural boundary

`CatalogPart` is the technical article. Existing `Product` remains the ecommerce/merchandising entity. Existing `SupplierProduct` remains a supplier-specific offer source.

```text
CatalogPart
  -> identifiers / OE / IAM / EAN
  -> attributes
  -> fitments
  -> cross references
  -> source assertions
       |
       +-> Product / ProductVariant (sellable listing)
       +-> SupplierProduct (offer mapping)
```

## Source boundary

`CatalogSource` provides technical knowledge. `Supplier` provides commercial stock/price/delivery. A company may eventually play both roles, but the domains remain separate.

## Import rule

No external source writes directly to canonical entities.

```text
fetch -> raw source record -> normalize -> match -> validate -> assert -> canonicalize -> index
```

The first implementation creates the source/raw layer and technical catalog tables. Source-specific canonicalizers (EEA, vPIC, lifeofcapo, manufacturer feeds) are added as adapters without changing the core schema.

## Commands

After migrations are run:

```bash
php artisan catalog:sources:sync EEA --mode=vehicles
php artisan catalog:sources:sync VPIC --mode=vehicles
php artisan catalog:sources:sync --all
```

Heavy imports run on the `catalog-imports` queue.

## Do not run yet

The migrations on the feature branch are intended to be executed by the project owner after the development tranche is reviewed/merged. No migration has been run remotely by this implementation work.
