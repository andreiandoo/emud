# Local Setup and Catalog Operations

This is the operator runbook for the automotive catalog inside eMUD.

## 1. Install the application

```bash
composer install
npm ci
npm run build
```

Create/configure `.env` if it does not already exist. The catalog is designed for PostgreSQL and uses Redis for queues/cache in the normal production topology.

Important environment values include the normal Laravel database settings plus:

```text
DB_CONNECTION=pgsql
QUEUE_CONNECTION=redis
CACHE_STORE=redis
ADMIN_EMAIL=...
ADMIN_PASSWORD=...
```

Generate an application key on a new environment:

```bash
php artisan key:generate
```

Do not regenerate `APP_KEY` on an existing environment containing encrypted source/supplier credentials.

## 2. Create/update the database

```bash
php artisan migrate
php artisan db:seed
```

`DatabaseSeeder` installs structural/reference data only: categories, attributes, commerce providers, catalog-source profiles and supplier profiles. It does not inject fake catalog records.

If `ADMIN_EMAIL` and `ADMIN_PASSWORD` are present in `.env`, `db:seed` also creates/updates the admin account. Run `php artisan optimize` **after** seeding: `DatabaseSeeder` reads `env()` directly, so a cached config makes it skip admin creation without reporting an error.

Re-seeding is safe on an operated installation. For a catalog source that already exists, the seeder refreshes only profile-owned fields (name, connector class, licence, capabilities) and adds settings keys introduced by a newer profile. Activation state, rights flags, field mapping and existing settings values stay under admin control and are never reverted by a deployment. When a shipped profile default differs from the stored value, the seeder prints which settings keys diverged instead of overwriting them.

## 3. Add deterministic test data

For a complete local test graph:

```bash
php artisan catalog:demo:seed --api-key --rebuild-search
```

This command creates synthetic `DEMO-*` vehicles, parts, fitments, cross-references, rights boundaries, supplier offers, an unresolved relation and an unmatched supplier product. It prints a fresh API key when `--api-key` is supplied.

The DEMO fixture is idempotent and is never inserted by the normal production seeder.

See `docs/catalog/DEMO_DATA.md` for the exact fixture and smoke-test requests.

## 4. Verify the installation

```bash
php artisan catalog:system:check
```

Machine-readable form:

```bash
php artisan catalog:system:check --json
```

The command verifies:

- database connectivity and required catalog tables;
- PostgreSQL/`pg_trgm` status;
- configured and active sources/schedules;
- vehicle/part/fitment counts;
- search projection population;
- unresolved relations/conflicts/supplier matching backlog;
- queue/cache configuration;
- DEMO fixture presence.

## 5. Run asynchronous workers

During development, use separate terminals:

```bash
php artisan queue:work --queue=notifications,catalog-search,catalog-canonicalization,catalog-matching,catalog-enrichment,catalog-imports,imports,workshops
php artisan schedule:work
```

**The queue list is not optional.** Every catalog and supplier job routes itself to a dedicated queue; none of them uses the default queue. A worker started as plain `php artisan queue:work` drains only the default queue (`REDIS_QUEUE`, often renamed per environment), reports `Processing jobs from the [...] queue`, finds nothing and exits. Imports then sit in Redis forever while `catalog:system:check` still reports the runtime as OK.

For the same reason, `php artisan queue:monitor default,...` is meaningless here — monitor the queues above instead. Note that `queue:monitor` reports queue depth, not whether a worker is alive; a dead worker and an empty queue look identical.

The long-running import queues come last in the list so that a multi-hour EEA import does not starve search projection and notification jobs.

### retry_after must outlast the longest job

`SyncCatalogSource` and `CanonicalizeCatalogSourceRecords` declare a two-hour timeout, but the Redis queue connection defaults to `retry_after=90`. Redis releases a reserved job back to the queue once `retry_after` elapses **even while the job is still running**, so with the default a long import is picked up by a second worker and the same source is ingested twice in parallel — with `tries=3`, up to three concurrent runs writing to staging. Nothing in the logs identifies this as a configuration fault.

Set this in every environment that runs imports:

```dotenv
REDIS_QUEUE_RETRY_AFTER=7260
```

`catalog:system:check` reports a `queue_retry` warning whenever the deployed value is not above the longest job timeout.

In production use long-running supervised queue workers with the same `--queue` list and `--timeout=1800`, plus the standard Laravel scheduler cron entry. Source and supplier cron expressions are stored in the database; Laravel's scheduler dispatcher evaluates them.

## 6. Admin catalog operations

Log in at:

```text
/admin/login
```

The catalog administration area provides:

- `/admin/catalog-explorer` — multi-directional vehicle/part/number/fitment/source/QA search;
- `/admin/catalog-schema` — all database tables, columns, indexes and foreign keys;
- `/admin/catalog-quality` — coverage and quality metrics;
- `/admin/catalog-sources` — technical data sources;
- `/admin/catalog-imports` — import-run history;
- `/admin/catalog-conflicts` — conflicting source assertions;
- `/admin/catalog-unresolved-relations` — unresolved cross-reference/supersession work queue;
- `/admin/catalog-supplier-matching` — supplier-product matching workbench;
- `/admin/catalog-api` — API consumers/keys.

Each saved catalog source can now be operated from its edit screen:

- test connector connection;
- run catalog mode immediately;
- run all enabled configured modes;
- add/remove per-mode cron schedules;
- set timezone and enable/disable each schedule;
- inspect previous dispatch/sync timestamps.

## 7. Built-in open/public vehicle sources

`php artisan db:seed` registers these profiles disabled by default:

- `EEA` — European Environment Agency passenger car/van technical registration data;
- `VPIC` — NHTSA vPIC VIN/reference data;
- `LIFEOFCAPO` — MIT vehicle taxonomy and generic parts taxonomy;
- `WIKIDATA` — CC0 alias/knowledge-graph enrichment.

Enable sources only after reviewing the profile and desired scope in the admin.

### EEA

After enabling `EEA`:

```bash
php artisan catalog:sources:sync EEA --mode=catalog
```

The current profile uses the verified EEA 2025 passenger-car and van datasets and canonicalizes technical configurations rather than individual registration rows.

### lifeofcapo

After enabling `LIFEOFCAPO`:

```bash
php artisan catalog:sources:sync LIFEOFCAPO --mode=catalog
```

The importer pins the upstream Git commit and stores release provenance.

### NHTSA vPIC

The vPIC profile can use HTTP VIN decoding or the official local PostgreSQL standalone database. To install/update the standalone database use the dedicated command documented in `docs/catalog/VPIC_STANDALONE.md` (if present in the current checkout) and configure `resolver_mode` in the source settings.

Reference-graph synchronization is performed through the normal source-sync mechanism using the supported vPIC modes configured for the source.

### Wikidata

Wikidata enrichment is intentionally a background/resumable workflow rather than a request-time dependency:

```bash
php artisan catalog:wikidata:enrich --help
```

Use `--help` on source-specific commands before large imports to review current batch/mode options.

## 8. Rebuild search

Full search projection rebuild:

```bash
php artisan catalog:search:rebuild --reset
```

The system also maintains search documents incrementally after canonical entity/identifier/rights changes. The full rebuild is primarily for bootstrap, recovery and validation.

## 9. Supplier feeds

Supplier commercial data stays separate from canonical technical truth.

Typical flow:

```text
Supplier feed artifact
  -> SupplierProduct
  -> catalog matcher
  -> CatalogPart
  -> SupplierOffer / stock / price
```

Supplier SFTP/HTTP feeds retain artifact-level provenance (path, timestamp, size, SHA-256) and have independent rights controls.

For a configured supplier:

```bash
php artisan suppliers:sync SUPPLIER_CODE --mode=catalog
php artisan suppliers:sync SUPPLIER_CODE --mode=stock
php artisan suppliers:sync SUPPLIER_CODE --mode=prices
```

Technical promotion from a supplier feed remains opt-in and requires both contractual derived-data rights and the explicit technical-promotion setting.

## 10. Useful maintenance commands

```bash
php artisan catalog:relations:resolve --limit=50000
php artisan catalog:search:rebuild --reset
php artisan catalog:system:check
php artisan queue:failed
```

Use the admin QA screens to resolve ambiguity rather than updating canonical rows directly in SQL.

## 11. Recommended first local validation

On a fresh development database:

```bash
php artisan migrate
php artisan db:seed
php artisan catalog:demo:seed --api-key --rebuild-search
php artisan catalog:system:check
```

Then log into the backend and verify:

1. Catalog Explorer finds `DEMO-FLT-100` and `Duster 1.5 4x4 DEMO`.
2. Opening a result reaches its detail page.
3. Database Schema shows the canonical/source/supplier tables.
4. Unresolved Relations contains `DEMO-MISSING-999`.
5. Supplier ↔ Catalog contains `SUP-DEMO-UNMAPPED`.
6. The API graph for `DEMO-FLT-100` exposes the public chain but not `DEMO-PRIVATE-900`.
7. Hilux + `DEMO-LFT-410` returns a conditional compatibility result with an engine-code constraint.

At that point the application is ready for real source ingestion and later dropshipping-supplier onboarding.
