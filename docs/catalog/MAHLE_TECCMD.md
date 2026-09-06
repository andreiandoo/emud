# MAHLE TecCMD onboarding

## Status

`MAHLE_TECCMD` is a **disabled-by-default permissioned supplier profile**. It is not an open public dataset and must not be enabled until customer access, file paths, sample files and contractual rights have been confirmed with MAHLE.

Official service page: <https://www.mahle-aftermarket.com/eu/en/services/teccmd/>

MAHLE currently states that TecCMD:

- is available free of charge to MAHLE customers;
- can be accessed online or automatically by SFTP;
- provides hourly stock and availability data;
- provides daily price data;
- provides daily material-master data;
- can be loaded into a back-office system and linked to websites or sales platforms.

These statements support the seeded `commerce_only` posture. They do **not**, by themselves, establish permission to republish the underlying dataset through the eMUD/RapidAPI product.

## Safe defaults

The seeder creates:

- supplier code: `MAHLE_TECCMD`
- protocol: `sftp`
- active: `false`
- data rights: `commerce_only`
- internal ingestion: allowed
- ecommerce use: allowed
- derived-data publication: disabled
- public API redistribution: disabled
- all sync schedules: disabled
- endpoints: unset
- credentials: empty
- field mapping: empty

Re-running the seeder uses `firstOrCreate`; it must never reset credentials, paths, mappings, activation state or edited schedules on an existing MAHLE supplier row.

## Recommended schedules

The disabled schedule templates reflect the cadence MAHLE publishes, with small offsets to avoid running exactly at the top of a vendor update window:

| Mode | Default template | Purpose |
| --- | --- | --- |
| `stock` | `5 * * * *` | hourly availability |
| `prices` | `40 1 * * *` | daily price file |
| `catalog` | `10 2 * * *` | daily material-master file |

Timezone: `Europe/Berlin`.

The actual timing can be changed in **Admin → Suppliers → Configure** after MAHLE confirms when files are delivered to the customer SFTP account.

## Activation checklist

1. Obtain TecCMD access from the MAHLE sales/customer-service representative.
2. Obtain the SFTP hostname, port and username.
3. Obtain the host fingerprint through a trusted channel and configure `host_fingerprint`.
4. Configure password or private-key authentication in encrypted supplier credentials.
5. Obtain the exact material-master, price and availability file paths/patterns.
6. Download representative sample files for every mode.
7. Confirm encoding, delimiter, decimal separator and file format.
8. Map the actual MAHLE headers to eMUD canonical supplier fields in `field_mapping`.
9. Confirm whether files include EAN/GTIN, MAHLE article number, product line/brand, descriptions, dimensions, status, supersessions, OE numbers, cross-references, applications or other technical fields.
10. Review the contractual terms for internal use, ecommerce display, derivative data and external/API redistribution.
11. Only then enable the supplier and the required schedules.
12. Run a manual catalog sync first and review `supplier_feed_artifacts`, `supplier_products` and catalog-match results before enabling recurring jobs.

## Credentials JSON

Example shape only—never commit real values:

```json
{
  "host": "sftp.vendor.example",
  "port": 22,
  "username": "customer-account",
  "password": "stored-encrypted-in-db",
  "host_fingerprint": "SHA256:..."
}
```

Private-key authentication is also supported through `private_key` and optional `passphrase`.

## Feed mapping

Do not guess field names from public MAHLE pages. Leave the seeded mapping empty until actual TecCMD samples are available.

Typical eMUD targets to look for in the samples are:

- `external_id`
- `sku`
- `manufacturer_part_number`
- `ean`
- `name`
- `description`
- `brand`
- `category_external_id`
- `cost_price`
- `recommended_retail_price`
- `currency`
- `stock_quantity`
- `stock_status`
- `lead_time_days`
- `images`
- `attributes`
- `fitments`

The generic SFTP parser supports CSV/TSV, XML and JSON. CSV/TSV is streamed. Every completed SFTP import records its precise remote artifact, size, supplier modification time, retrieval time and SHA-256 checksum.

## Technical catalog promotion

TecCMD commercial data should first land in the supplier layer:

`MAHLE SFTP → SupplierFeedArtifact → SupplierRecord → SupplierProduct / SupplierOffer`

Identifiers are then matched to `CatalogPart` using the normal supplier-to-catalog matching pipeline.

Do **not** automatically treat every supplier field as canonical technical truth. If MAHLE provides application, OE-reference, supersession or technical-attribute data and the agreement permits us to retain/derive it, onboard those fields through a rights-aware `CatalogSource`/manufacturer canonicalization path with assertions and provenance.

## Public API rule

The standalone catalog/RapidAPI serializer must continue to exclude MAHLE-derived fields while `allow_api_redistribution = false`. Commercial availability or customer access is not equivalent to redistribution permission.
