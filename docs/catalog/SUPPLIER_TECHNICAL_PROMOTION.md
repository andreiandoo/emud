# Supplier technical-data promotion

This layer converts technical fields from permissioned manufacturer/distributor feeds into the canonical automotive catalog without treating supplier commerce data as canonical truth by default.

## Pipeline

```text
Supplier transport (SFTP/API/file)
  -> SupplierSyncRun
  -> SupplierFeedArtifact (path, timestamp, SHA-256)
  -> SupplierProduct + technical_payload
  -> supplier catalog matching
  -> rights + promotion policy gate
  -> managed CatalogSource: SUPPLIER_<CODE>
  -> CatalogSourceRelease + CatalogSourceRecord
  -> ManufacturerPartCanonicalizer
  -> CatalogPart / identifiers / attributes / fitments / relations
  -> CatalogSourceAssertion
```

Every promoted canonical value remains traceable to the supplier product, supplier sync run, exact feed artifact, managed catalog source and source record that introduced it.

## Rights gates

Technical promotion requires both:

1. `suppliers.allow_derived_data = true`
2. `suppliers.settings.technical_promotion_enabled = true`

Transport access or ecommerce permission alone is not sufficient.

The managed `CatalogSource` mirrors the supplier rights flags. In particular, `allow_api_redistribution` remains independent from internal/derived/ecommerce rights. A permissioned feed can therefore enrich the internal/ecommerce catalog while its facts stay excluded from the public Catalog API.

## Safe default: augment only

`technical_promotion_create_parts` defaults to false.

In this mode, a supplier row may enrich a canonical part only when the exact `brand + normalized MPN` identity already exists. Otherwise the row is marked `awaiting_canonical_part` and retained for later review/reprocessing.

Set:

```json
{
  "technical_promotion_enabled": true,
  "technical_promotion_create_parts": true
}
```

only for sources whose contract and data quality justify creating new canonical technical articles.

## Normalized technical payload

The generic supplier mapper supports these keys:

| Key | Purpose |
|---|---|
| `brand` | article manufacturer/brand |
| `manufacturer_part_number` | canonical MPN candidate |
| `ean` | EAN/GTIN identifier |
| `category_external_id` | supplier category value for mapping |
| `attributes` | structured technical attributes |
| `fitments` | structured vehicle applications |
| `oe_numbers` | OE identifiers |
| `iam_numbers` | aftermarket/IAM identifiers |
| `cross_references` | equivalent/interchange references |
| `supersessions` | old/new/superseding article references |

Example field mapping:

```json
{
  "external_id": "article.id",
  "name": "article.description",
  "brand": "article.brand",
  "manufacturer_part_number": "article.mpn",
  "ean": "article.gtin",
  "oe_numbers": "references.oe",
  "iam_numbers": "references.iam",
  "cross_references": "references.cross",
  "supersessions": "references.supersessions",
  "attributes": "technical.attributes",
  "fitments": "applications"
}
```

Do not guess vendor columns. Configure these paths only after receiving and inspecting an authorized sample export.

## Reference shapes

A simple string is accepted where only a number is available. Rich reference objects are preferred.

```json
{
  "number": "90915-YZZD2",
  "scheme": "OE",
  "make": "Toyota"
}
```

Cross reference:

```json
{
  "brand": "MANN-FILTER",
  "number": "W 68/3",
  "scheme": "MPN",
  "relation_type": "equivalent",
  "confidence": 98
}
```

Supersession:

```json
{
  "brand": "MAHLE",
  "number": "OC 456",
  "scheme": "MPN",
  "direction": "superseded_by"
}
```

## Deferred relation graph

A cross-reference or supersession must not be discarded merely because the target article has not been ingested yet.

Unresolved targets are stored in `catalog_unresolved_part_relations`. The hourly command:

```bash
php artisan catalog:relations:resolve --limit=50000
```

retries them as the identifier graph grows. Once a target resolves, the system creates a `catalog_part_relations` edge while retaining the unresolved-row history and source provenance.

## Fitments

Supplier fitments are promoted only when they resolve to an existing `VehicleConfiguration` through either:

- `configuration_id`, or
- `vehicle_identifier_scheme` + `vehicle_identifier`.

Unresolved applications create catalog conflicts instead of silently applying broad make/model compatibility.

## Conflict policy

A commerce match and a technical identity are independent signals.

If `SupplierProduct.catalog_part_id` already points to part A but the technical `brand + MPN` resolves to part B:

- the existing supplier mapping is not overwritten;
- technical data may enrich the exact technical identity B;
- a `technical_identity_mismatch` catalog conflict is opened;
- the supplier product receives `technical_promotion_status = conflict`.

## Promotion statuses

- `not_eligible` — no technical decision made yet
- `blocked_rights` — derived-data rights do not allow promotion
- `disabled` — promotion disabled in supplier settings
- `insufficient_identity` — missing brand or MPN
- `pending` — ready for promotion
- `awaiting_canonical_part` — augment-only mode and identity does not yet exist
- `promoted` — canonicalized successfully
- `conflict` — commerce mapping conflicts with technical identity
- `skipped` — canonicalizer deliberately skipped the row
- `failed` — technical processing error

## Manufacturer activation checklist

For each manufacturer/distributor feed:

1. obtain authorized feed access and contract/terms;
2. set `allow_internal_data`, `allow_ecommerce_data`, `allow_derived_data`, and API rights independently;
3. archive a sample/feed artifact and verify its format;
4. configure exact field mappings;
5. keep `technical_promotion_create_parts=false` initially;
6. run catalog ingestion and inspect matching/promotion conflicts;
7. validate OE/IAM references and fitments against source documentation;
8. enable canonical-part creation only when source quality and contractual rights support it;
9. enable public API redistribution only when explicitly permitted.
