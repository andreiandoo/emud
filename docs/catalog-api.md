# Automotive Catalog API v1

The catalog API exposes only canonical facts whose source assertions are explicitly approved for API redistribution. A record being visible internally does not automatically make it API-visible.

## Authentication

Every `/api/v1/*` request requires an active catalog API key. Either form is accepted:

```http
X-API-Key: <token>
```

or:

```http
Authorization: Bearer <token>
```

API keys are stored hashed. Consumers have monthly quotas; responses include `X-RateLimit-Limit` and `X-RateLimit-Remaining` headers.

The machine-readable contract is `public/openapi/catalog-v1.yaml`, and `docs/catalog/API.md` is the full reference. In short:

- list endpoints page with `page` and `per_page` (max 100; `limit` still works as an alias) and report `total`, `total_pages` and `has_more`;
- `/changes` pages by opaque `cursor`, because one import writes many events sharing a timestamp;
- every response names the sources behind its rows in `meta.attribution`, with `meta.license_notice` where credit is required;
- `/makes` → `/makes/{id}/models` → `/models/{id}/generations` → `/generations/{id}/vehicles` is the car-picker cascade, listing only branches that end in a publishable vehicle;
- `POST /vin/batch` and `POST /parts/by-number/batch` resolve up to 50 items per request;
- `/parts/lookup?number=` and `/parts/graph?number=` are the forms that can carry a number containing a slash, which a path segment cannot;
- failures answer `{"error": {"code", "message"}}` with a stable code.

## VIN resolver modes

The `VPIC` catalog source supports four resolver modes through its `settings` JSON:

- `http` — NHTSA vPIC HTTP API only. This remains the default.
- `postgres` — local vPIC PostgreSQL database only.
- `postgres_then_http` — local database first, HTTP only when the local decoder is unavailable.
- `http_then_postgres` — HTTP first, local database only when HTTP is unavailable.

Example:

```json
{
  "resolver_mode": "postgres_then_http",
  "api_base_url": "https://vpic.nhtsa.dot.gov",
  "database_connection": "pgsql",
  "database_schema": "vpic"
}
```

The public VIN endpoint still requires the `VPIC` source to be active **and** approved for API redistribution.

## Installing the official vPIC PostgreSQL database

NHTSA publishes a PostgreSQL 17 standalone vPIC database on the official Downloads page:

- https://vpic.nhtsa.dot.gov/api/
- https://vpic.nhtsa.dot.gov/Downloads

Restore the current PostgreSQL distribution into the PostgreSQL server used by the configured Laravel connection. The official PostgreSQL package restores its objects into the `vpic` schema. The application does not copy or mutate those tables; it calls the official decode function and then maps the decoded vehicle into the application's canonical `vehicle_configurations` graph.

Verify the installation directly in PostgreSQL before enabling local resolution:

```sql
select * from vpic.spVinDecode('1M8GDM9AXKP042788');
```

Then configure `database_connection` and `database_schema` on the `VPIC` source. If the standalone database is restored into the application's existing PostgreSQL database, `database_connection` can remain `pgsql`. If it lives in another database, add a dedicated Laravel database connection and reference its connection name here.

### Operational notes

- vPIC primarily represents vehicles intended for the U.S. market; European-only VINs can remain partial or unmapped.
- A successful VIN decode does not prove exact parts fitment. Exact fitment still depends on the canonical configuration and, where applicable, engine/gearbox/drivetrain/options/production ranges.
- Local and HTTP vPIC backends use the same canonical matcher and produce the same API resolution states: `invalid`, `unsupported`, `unavailable`, `basic_only`, `ambiguous`, or `high_confidence`.
- Resolver failures do not expose database or network exception details to API consumers.

## Data publication rule

API serialization is evidence-aware. Parent entities and individual identifiers/part numbers are filtered separately so a redistributable part cannot accidentally expose an OE number learned only from a restricted source.

Visibility requires the assertion **and** the live source flag. An assertion's `api_redistributable` is a snapshot taken when it was written, and nothing rewrites it, so clearing **Publicare API** on a source used to leave its already-published rows still being served. The read scope now also requires `catalog_sources.allow_api_redistribution`, which makes a revocation take effect immediately on every existing assertion, with no backfill and no cache to wait out.

Use the admin areas **Catalog API**, **Surse catalog**, **Conflicte & QA**, and **Calitate & acoperire** before enabling a source for public redistribution.
