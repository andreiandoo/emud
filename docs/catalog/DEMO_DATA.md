# Automotive Catalog DEMO Data

The catalog has a deterministic local-development dataset for exercising the canonical automotive graph without importing or pretending to own licensed OEM/TecDoc data.

All identifiers beginning with `DEMO-`, `SUP-DEMO-`, `DEMO_` or `S-D-` are synthetic fixtures. They are not authoritative vehicle, OEM, aftermarket, supplier or fitment records.

## One-command setup

Run migrations, then:

```bash
php artisan catalog:demo:seed --api-key --rebuild-search
```

The command is idempotent. Re-running it updates the same fixture entities instead of multiplying them.

`--api-key` revokes the previous active `DEMO CLI` key, issues a fresh token and prints the plaintext token once.

`--rebuild-search` resets and queues the canonical part and vehicle search projections. With `QUEUE_CONNECTION=sync` this completes inline; with an asynchronous queue, run the normal queue worker.

The demo fixture is intentionally **not** called by `DatabaseSeeder`, so a normal production/reference seed does not receive fake catalog data. It can also be invoked directly:

```bash
php artisan db:seed --class=Database\\Seeders\\AutomotiveCatalogDemoFixtureSeeder
```

`AutomotiveCatalogDemoFixtureSeeder` is the supported fixture entrypoint. It runs the canonical demo-data seeder and then normalizes demo supplier mapping state names to the same `mapped_auto` contract used by the production matcher.

## What is seeded

### Rights boundaries

- `DEMO_PUBLIC` — internal + ecommerce + derived + API redistribution.
- `DEMO_COMMERCE_ONLY` — internal/ecommerce only; must not appear in the public API.
- `DEMO_RESTRICTED` — internal reference only; must never appear in the public API.
- `DEMO_SUPPLIER_TECHNICAL` — synthetic permissioned supplier technical feed.

These sources are deliberately different so local testing can prove that public API visibility is an entitlement calculation, not simply “row exists in database”.

### Vehicle configurations

Four recognizable but non-authoritative DEMO configurations are provided:

- Dacia Duster II — `Duster 1.5 4x4 DEMO`
- Toyota Hilux VIII — `Hilux 2.8 4WD DEMO`
- Suzuki Jimny IV — `Jimny 1.5 4WD DEMO`
- Jeep Wrangler JL — `Wrangler JL 2.0 4x4 DEMO`

Each has a synthetic `DEMO_VEHICLE_ID`, canonical fingerprint, engine/configuration data, API-public identity assertion, and a small set of technical facts.

### Canonical parts

The fixture covers replacement parts and off-road accessories, including:

- oil and air filters;
- brake pads/discs;
- wheel bearing;
- shock absorber;
- 40 mm lift kit;
- skid plate;
- recovery point;
- one commerce-only part;
- one restricted part.

Useful identifiers:

```text
DEMO-FLT-100   public graph seed
DEMO-FLT-110   equivalent part
DEMO-FLT-120   first supersession
DEMO-FLT-130   second supersession
DEMO-LFT-410   conditional Hilux fitment
DEMO-PRIVATE-900 restricted identity
DEMO-ECO-800   ecommerce-only identity
```

The filter graph contains:

```text
DEMO-FLT-100 <-> DEMO-FLT-110        equivalent, confidence 97
DEMO-FLT-100  -> DEMO-FLT-120        superseded_by, confidence 92
DEMO-FLT-120  -> DEMO-FLT-130        superseded_by, confidence 84
DEMO-FLT-100 <-> DEMO-PRIVATE-900    restricted relation source; MUST NOT leak
```

A deliberately unresolved relation to `DEMO-MISSING-999` is seeded so `/admin/catalog-unresolved-relations` is immediately testable.

### Fitment

The fixture includes confirmed and conditional fitments. In particular, `DEMO-LFT-410` on the demo Hilux is conditional on the synthetic engine code `DEMO-H28`, which exercises structured fitment constraints.

A public-source fitment deliberately points to the restricted part `DEMO-PRIVATE-900`. This is a security regression fixture: the public vehicle-parts and compatibility APIs must still suppress/reject that part because the canonical part identity itself is not API-redistributable.

### Supplier commerce

`DEMO_DISTRIBUTOR_EU` provides four mapped products/offers and one intentionally unmatched product:

```text
SUP-DEMO-001       mapped oil filter offer
SUP-DEMO-002       mapped brake pad offer
SUP-DEMO-003       mapped lift kit offer
SUP-DEMO-004       mapped skid plate offer
SUP-DEMO-UNMAPPED  intentional supplier matching queue item
```

Offers include cost, recommended retail price, RON currency, VAT, stock and lead time. A weak match candidate is also staged for the unmatched row so the supplier-matching admin flow can be exercised.

## API smoke tests

Use the key printed by `catalog:demo:seed --api-key`:

```bash
curl -H "X-API-Key: $API_KEY" \
  "http://localhost/api/v1/parts/by-number/DEMO-FLT-100/graph?scheme=MPN&depth=3"
```

Expected graph behavior:

- `DEMO-FLT-100`, `110`, `120`, `130` are present;
- path confidence progresses `100 -> 97 / 92 -> 84` depending on path;
- `DEMO-PRIVATE-900` is absent even though a restricted relation to it exists.

Vehicle search:

```bash
curl -H "X-API-Key: $API_KEY" \
  "http://localhost/api/v1/vehicles/search?q=Duster"
```

Vehicle parts, after taking the Duster numeric vehicle ID printed by the command:

```bash
curl -H "X-API-Key: $API_KEY" \
  "http://localhost/api/v1/vehicles/<DUSTER_ID>/parts?limit=100"
```

Neither `DEMO-PRIVATE-900` nor `DEMO-ECO-800` may be returned.

Conditional compatibility, using the Hilux vehicle ID and `prt_...` ID for `DEMO-LFT-410` printed by the command:

```bash
curl -X POST \
  -H "X-API-Key: $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"vehicle_id":<HILUX_ID>,"part_id":"prt_<LIFT_KIT_PUBLIC_ID>"}' \
  "http://localhost/api/v1/compatibility/check"
```

Expected status: `conditional`, with an `engine_code` constraint.

## Admin smoke tests

After logging in as an admin:

- `/admin/catalog-explorer` — search for `DEMO-` records;
- `/admin/catalog-unresolved-relations` — inspect/retry/link `DEMO-MISSING-999`;
- `/admin/catalog-supplier-matching` — inspect `SUP-DEMO-UNMAPPED`;
- `/admin/catalog-quality` — see the fixture contribute to coverage;
- `/admin/catalog-api` — inspect the `Catalog DEMO Local` consumer and keys.

## Automated regression coverage

`tests/Feature/AutomotiveCatalogDemoSeederTest.php` verifies:

- seeder idempotency;
- expected demo surface counts;
- transitive public graph traversal and confidence;
- restricted graph edge suppression;
- vehicle-parts publication-scope enforcement;
- compatibility publication-scope enforcement;
- conditional fitment constraint serialization;
- unresolved relation and supplier matching queue fixtures.

`tests/Feature/CatalogPublicationBoundaryTest.php` additionally verifies:

- demo supplier states normalize to `mapped_auto`;
- a public part-number source cannot expose a restricted canonical part;
- public VIN matching cannot return or suggest a canonical vehicle whose identity is not API-publishable.
