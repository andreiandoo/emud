# Catalog API — v1

The catalog API is authenticated independently from ecommerce users. Direct API clients and marketplace/gateway clients resolve to the same `CatalogApiConsumer` model and are subject to the same publication-rights rules.

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

## Quotas and request metering

Direct and RapidAPI traffic use the same atomic monthly usage meter. Quota reset/check/increment is performed under a database row lock to prevent concurrent check-then-increment overshoot.

For a positive local monthly quota, successful responses include:

```http
X-RateLimit-Limit: 10000
X-RateLimit-Remaining: 9999
```

A quota of `0` means unlimited locally and these two headers are omitted. Successful responses also include `X-Catalog-Auth-Channel: native|rapidapi`.

The per-minute Laravel API limiter is keyed to the resolved catalog consumer after authentication, not to the gateway IP.

## Rights enforcement

An entity is publicly searchable only when at least one published `catalog_source_assertion` for that entity is marked `api_redistributable=true`.

Fitment, number and part-relation graph endpoints additionally require the underlying source evidence to have `allow_api_redistribution=true`.

Internal admin/ecommerce visibility can therefore be broader than public API visibility.

## Endpoints

```text
GET  /api/v1/vehicles/search
GET  /api/v1/vehicles/{id}
GET  /api/v1/vehicles/{id}/parts

GET  /api/v1/parts/search
GET  /api/v1/parts/by-number/{number}
GET  /api/v1/parts/by-number/{number}/graph
GET  /api/v1/parts/{publicId}

POST /api/v1/compatibility/check
GET  /api/v1/coverage
GET  /api/v1/changes
```

The machine-readable contract is published in `public/openapi/catalog-v1.yaml`.

## Cross-reference / supersession graph

`GET /api/v1/parts/by-number/{number}/graph` resolves a known identifier to seed canonical parts, then traverses canonical part relations.

Query parameters:

- `scheme` — optional identifier namespace such as `MPN`, `OE`, `IAM`, `EAN_GTIN`;
- `depth` — `0..4`, default `2`;
- `max_nodes` — `1..250`, default `100`;
- `max_edges` — `1..2000`, default `800`.

Example:

```http
GET /api/v1/parts/by-number/OC%20123/graph?scheme=MPN&depth=2&max_nodes=100&max_edges=800
```

The response preserves graph structure:

```json
{
  "data": {
    "query": {
      "number": "OC 123",
      "scheme": "MPN",
      "depth": 2,
      "max_nodes": 100,
      "max_edges": 800
    },
    "seeds": ["prt_..."],
    "nodes": [
      {"distance": 0, "path_confidence": 100, "part": {"id": "prt_..."}},
      {"distance": 1, "path_confidence": 98, "part": {"id": "prt_..."}}
    ],
    "edges": [
      {
        "from": "prt_...",
        "to": "prt_...",
        "relation_type": "equivalent",
        "directed": false,
        "confidence": 98,
        "source": "SUPPLIER_MAHLE"
      }
    ],
    "truncated": false
  }
}
```

`path_confidence` is the confidence of the strongest path discovered to that node. For a path with several edges, its confidence is the minimum edge confidence along that path; when several paths reach the same part, the highest such path confidence is retained. Seed nodes start at `100`.

The traversal is cycle-safe and bounded. An edge is returned only when the relation source permits API redistribution, and both endpoint parts must independently satisfy the public publication scope. This prevents a public identifier from being used as a bridge into restricted technical data.

`truncated=true` means the result reached the node budget, edge budget, or bounded relation scan and should not be interpreted as a complete connected component. The explicit edge budget is important for highly connected aftermarket cross-reference components where the number of relations can grow much faster than the number of parts.

Reverse relation lookup is indexed separately so traversal remains efficient whether a part appears as the source or target of a canonical relation.

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

The admin surface `/admin/catalog-unresolved-relations` shows cross-references and supersessions that could not yet be mapped to a canonical target. Operators can:

- retry automatic matching;
- inspect source/record provenance and confidence;
- manually link a known canonical part without changing the raw source row;
- reject a bad source reference and record a reviewer note;
- reopen rejected references later.

Retry attempts and reviewer metadata are retained so the queue distinguishes never-reviewed data from repeatedly unresolved data.
