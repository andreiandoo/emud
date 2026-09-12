# Catalog API — v1

The catalog API is authenticated independently from ecommerce users. Direct API clients and marketplace/gateway clients resolve to the same `CatalogApiConsumer` model and are subject to the same publication-rights rules.

The machine-readable contract is `public/openapi/catalog-v1.yaml`, and it is the source of truth for parameters and response shapes. It is kept in step with `routes/api.php`; a route that is not in the spec is a bug in one of the two.

## Authentication

### Direct eMUD API

Send either:

```http
X-API-Key: emud_...
```

or:

```http
Authorization: Bearer emud_...
```

Keys are stored as SHA-256 hashes. The plaintext key is returned only once when issued.

### RapidAPI provider traffic

RapidAPI traffic does not need a native eMUD key. The backend authenticates the provider request with the configured `X-RapidAPI-Proxy-Secret` and maps `X-RapidAPI-User` to a persistent external identity and `CatalogApiConsumer`.

Do not use or trust a customer's `X-RapidAPI-Key` as an eMUD backend credential. See `docs/catalog/RAPIDAPI.md` for provider configuration, subscription mapping, optional host pinning and failure behavior.

Any request carrying provider-side RapidAPI headers is committed to the RapidAPI authentication path. A bad/missing proxy secret cannot fall back to a simultaneously supplied native eMUD API key.

## Response envelope

Every endpoint answers `{"data": …}`, with a `meta` alongside it when there is paging or attribution to report. Failures answer `{"error": {"code": …, "message": …}}` with a stable machine-readable `code`; branch on the code, never on the message. `VALIDATION_FAILED` adds `error.details` with field-level errors.

## Paging

List endpoints take `page` and `per_page` (max 100). `limit` is still accepted as an alias for `per_page`, so a consumer written against 1.2.0 keeps working, and `meta.limit` is still reported next to `meta.per_page`.

```http
GET /api/v1/parts/search?q=filter&per_page=50&page=3
```

```json
{
  "data": [],
  "meta": {"page": 3, "per_page": 50, "limit": 50, "has_more": true, "total": 812, "total_pages": 17}
}
```

`with_total=0` skips the count query for callers that only walk forward; `total` and `total_pages` are then absent and `has_more` still tells the caller whether to continue.

Two paging details that are behaviour, not accident:

- **Ranked search has a window.** With `q`, results come from the search index, which returns at most 500 candidates (`CatalogSearchService::entityIds`). A page past that window runs out of candidates before it runs out of matches. Narrow with the filters instead of paging deeper.
- **Lists page over entities, not over evidence.** `/vehicles/{id}/parts` pages over distinct parts, so a part fitted front-left and front-right is one result; `/parts/by-number/{number}` pages over the parts a number resolves to, not over the identifier rows that matched. Both used to dedupe *after* limiting, which returned short pages with repeats across them.

The change feed pages by cursor instead — see below.

## Attribution

`meta.attribution` names the sources behind the rows in the response, with licence and licence URL, and `meta.license_notice` carries the credit a consumer must reproduce when any of those sources requires it:

```json
"meta": {
  "attribution": [
    {"code": "EEA", "name": "European Environment Agency vehicle registrations",
     "license": "EEA reuse policy / CC BY", "license_url": "https://…", "attribution_required": true}
  ],
  "license_notice": "Data in this response must be credited to: European Environment Agency vehicle registrations (EEA reuse policy / CC BY)."
}
```

This is not decoration. The open datasets this catalogue can republish are largely licensed on condition of credit — EEA is CC BY, NHTSA asks that attribution survives copying — so publishing them without naming them would breach the licence the product depends on. A source that may not be republished is never named either: which restricted catalogues the platform holds is not a consumer's business.

`GET /api/v1/sources` lists the full set of redistributable sources and their terms, for a consumer that needs to render a credit line once rather than per response.

## Quotas and request metering

Direct and RapidAPI traffic use the same atomic monthly usage meter. Quota reset/check/increment is performed under a database row lock to prevent concurrent check-then-increment overshoot.

For a positive local monthly quota, successful responses include:

```http
X-RateLimit-Limit: 10000
X-RateLimit-Remaining: 9999
```

A quota of `0` means unlimited locally and these two headers are omitted. Successful responses also include `X-Catalog-Auth-Channel: native|rapidapi`.

The per-minute Laravel API limiter is keyed to the resolved catalog consumer after authentication, not to the gateway IP — which matters because all RapidAPI traffic arrives from the same proxy addresses.

A batch request costs one metered request whatever its size; the item ceiling (`catalog_api.batch.max_items`, default 50) is what bounds it.

## Caching

Read endpoints are served through `ApiResponseCache`. Entries are keyed by path and sorted query string plus a catalog version stamp, and **not** by consumer: what may be published is decided by source rights, not by who is asking, so two consumers sending the same query share one entry. Quota headers are written onto the live response, so a cache hit still reports the caller's own remaining quota.

`CatalogVersionObserver` bumps the stamp whenever a part, number, fitment, relation, assertion, source, vehicle configuration, identifier or category changes, which retires every cached page at once. That is deliberately wider than a TTL would need to be: rights must never be served stale, because the stale answer is the one that republishes data the owner has just withdrawn.

Responses carry `X-Catalog-Cache: hit|miss`. `CATALOG_API_CACHE_ENABLED=false` switches it off; `CATALOG_API_CACHE_TTL` (default 300s) is the backstop for a version bump that never arrives.

## Rights enforcement

An entity is publicly visible only while **both** hold:

1. a published `catalog_source_assertion` for it is marked `api_redistributable = true`; and
2. the source behind that assertion still has `allow_api_redistribution = true`.

The second condition is what makes a revocation take effect. The assertion's flag is a snapshot written by `SourceAssertionWriter` at canonicalization time, and nothing rewrites those snapshots — so checking only the assertion meant that clearing a source's redistribution rights in the admin left every already-published row still being served. `CatalogPublicationScope::visibleEntity()` therefore joins `catalog_sources` and requires the live flag too. The change feed does the same through `catalog_change_events.catalog_source_id`.

Fitment, number and part-relation graph endpoints additionally require the underlying source evidence to have `allow_api_redistribution = true`. Internal admin/ecommerce visibility can therefore be broader than public API visibility.

## Endpoints

```text
Vehicle identification
GET  /api/v1/vin/{vin}
POST /api/v1/vin/batch

Vehicle tree — the cascade a storefront's car picker walks
GET  /api/v1/makes
GET  /api/v1/makes/{make}/models
GET  /api/v1/models/{model}/generations
GET  /api/v1/generations/{generation}/vehicles
GET  /api/v1/vehicles/search
GET  /api/v1/vehicles/{id}

Parts
GET  /api/v1/vehicles/{id}/parts
GET  /api/v1/parts/search
GET  /api/v1/parts/lookup?number=…
GET  /api/v1/parts/by-number/{number}
POST /api/v1/parts/by-number/batch
GET  /api/v1/parts/graph?number=…
GET  /api/v1/parts/by-number/{number}/graph
GET  /api/v1/parts/{publicId}
POST /api/v1/compatibility/check

Catalog
GET  /api/v1/categories
GET  /api/v1/sources
GET  /api/v1/coverage
GET  /api/v1/changes
```

### Vehicle tree

Each level lists only branches that end in a vehicle this API may publish, so a picker built on it never offers a choice that leads nowhere. This is not a nicety: vPIC contributes every registered US manufacturer as VIN-decoding reference data — trailer builders and welding shops included — which left 12,275 of 13,138 makes with no vehicle behind them.

`/models/{model}/generations?year=2010` filters generations by a year their production span covers, because a customer knows the year of their car long before they know which generation that lands in.

Identifiers are returned prefixed (`mk_12`, `mdl_34`, `gen_56`, `veh_78`, `prt_…`) and path parameters take the bare integer, matching how `veh_`/`prt_` already worked in 1.2.0.

### Part numbers contain slashes

Mann sells a filter called `W 68/3`, and a slash cannot survive a path segment even percent-encoded — the request never reaches the route. `GET /api/v1/parts/lookup?number=…` is therefore the form that works for every number, and the same applies to `GET /api/v1/parts/graph?number=…`. The path forms stay for the numbers where they read better.

### Batch lookups

`POST /api/v1/parts/by-number/batch` resolves up to 50 numbers with a fixed number of queries whatever the batch size — it is meant for a shop matching its own catalogue against this one, where one HTTP request per number is the difference between a job that finishes overnight and one that does not. A number with no match comes back with an empty `matches` array rather than being omitted, so the caller can tell which of its own rows failed.

`POST /api/v1/vin/batch` is the same idea for VINs. A malformed VIN is reported as its own `invalid` row rather than failing the batch: one bad row in a customer's spreadsheet should not cost them the other forty-nine.

### Change feed

```http
GET /api/v1/changes?per_page=100
GET /api/v1/changes?cursor=MjAyNi0wOS0xMlQxMDowMDowMCswMDowMHwxNDIx
```

Paged by opaque cursor rather than by `since` alone. An import writes its events in batches that share one `occurred_at`, so a page boundary routinely falls inside such a group; resuming from the last timestamp would either skip the rest of that group or replay it forever. The cursor carries the row id as a tiebreaker. It is issued on every response, including an empty one, so a consumer that drains the feed can store one cursor and come back to it later. `since` seeds a first read only and is ignored once a cursor is supplied. A cursor this endpoint did not issue is rejected with `CURSOR_INVALID` rather than silently ignored.

### Cross-reference / supersession graph

`GET /api/v1/parts/graph?number=…` (or the path form) resolves a known identifier to seed canonical parts, then traverses canonical part relations.

Query parameters: `scheme` (`MPN`, `OE`, `IAM`, `EAN_GTIN`), `depth` (`0..4`, default `2`), `max_nodes` (`1..250`, default `100`), `max_edges` (`1..2000`, default `800`).

`path_confidence` is the confidence of the strongest path discovered to that node: for a path with several edges it is the minimum edge confidence along that path, and when several paths reach the same part the highest such path confidence is retained. Seed nodes start at `100`.

The traversal is cycle-safe and bounded. An edge is returned only when the relation source permits API redistribution, and both endpoint parts must independently satisfy the public publication scope — this prevents a public identifier from being used as a bridge into restricted technical data.

`truncated=true` means the result reached the node budget, edge budget, or bounded relation scan and should not be interpreted as a complete connected component. The explicit edge budget matters for highly connected aftermarket cross-reference components, where relations grow much faster than parts.

Reverse relation lookup is indexed separately so traversal stays efficient whether a part appears as the source or the target of a canonical relation.

## What the endpoints can actually return

The API surface is complete and tested, but an endpoint can only serve data that has been imported **and** cleared for redistribution. Two things gate that, and neither is code:

- All four open sources (`EEA`, `VPIC`, `LIFEOFCAPO`, `WIKIDATA`) ship `is_active = false` and must be enabled and imported by the owner.
- Every real supplier profile ships `allow_api_redistribution = false`. `MAHLE_TECCMD` is `commerce_only`, and as `docs/catalog/MAHLE_TECCMD.md` records, MAHLE's published terms do not by themselves establish permission to republish that dataset through this API.

So the parts side — `/parts/*`, `/vehicles/{id}/parts`, `/compatibility/check` and the graph — has no licensed source behind it yet and will answer with empty collections until one is contracted. The vehicle side (`/vin`, the tree, `/vehicles/*`) is servable from EEA and vPIC once those imports are run. `GET /api/v1/coverage` reports exactly what the current database can see, per source, which is the honest way to check before listing a plan.

## API consumer administration

API consumers, native keys and external identities are managed from the Catalog API admin surface. Keys should be issued with the minimum required scope/quota and rotated if exposed. RapidAPI identities are created automatically on first valid request when `RAPIDAPI_AUTO_PROVISION=true`.

Programmatic native-key issuance remains available for development:

```php
$consumer = App\Models\CatalogApiConsumer::create([
    'public_id' => (string) Illuminate\Support\Str::ulid(),
    'name' => 'Development',
    'slug' => 'development',
    'plan' => 'basic',
    'monthly_quota' => 10000,
]);

$issued = App\Models\CatalogApiKey::issue($consumer);
$issued['token']; // save now; it is not stored in plaintext
```

## Source pipeline

Raw import:

```bash
php artisan catalog:sources:sync EEA --mode=vehicles
```

Canonicalization:

```bash
php artisan catalog:sources:canonicalize EEA
```

Deferred relation resolution:

```bash
php artisan catalog:relations:resolve --limit=50000
```

If a source has `settings.auto_canonicalize=true`, canonicalization is queued automatically after a successful source download.

## Relation QA

The admin surface `/admin/catalog-unresolved-relations` shows cross-references and supersessions that could not yet be mapped to a canonical target. Operators can retry automatic matching; inspect source/record provenance and confidence; manually link a known canonical part without changing the raw source row; reject a bad source reference with a reviewer note; and reopen rejected references later.

Retry attempts and reviewer metadata are retained so the queue distinguishes never-reviewed data from repeatedly unresolved data.
