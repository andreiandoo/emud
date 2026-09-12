# RapidAPI provider integration

The catalog API supports two independent access channels over the same `/api/v1` endpoints:

1. direct eMUD API consumers using `X-API-Key` or `Authorization: Bearer`;
2. RapidAPI marketplace traffic authenticated at the eMUD backend through RapidAPI provider headers.

Both channels resolve to `CatalogApiConsumer` and use the same atomic usage meter and catalog publication/rights rules.

## Trust boundary

The eMUD backend does **not** trust `X-RapidAPI-Key` as caller identity. That key is used by a RapidAPI customer when talking to Rapid Runtime and should not become an eMUD backend credential.

Provider traffic is accepted only when the backend receives a valid:

```http
X-RapidAPI-Proxy-Secret: <configured provider proxy secret>
```

The request must also include:

```http
X-RapidAPI-User: <RapidAPI caller identity>
```

and normally:

```http
X-RapidAPI-Subscription: BASIC|PRO|ULTRA|MEGA|CUSTOM
```

If any provider-side RapidAPI header is present, the request is treated as a RapidAPI request and **cannot fall back** to native eMUD API-key authentication. This prevents an invalid proxy secret from being bypassed by also supplying a valid native API key.

## Configuration

Configure the provider secret copied from the RapidAPI provider configuration:

```dotenv
RAPIDAPI_PROXY_SECRET=replace-with-the-provider-proxy-secret
```

Optional host pinning:

```dotenv
RAPIDAPI_EXPECTED_HOST=automotive-catalog.p.rapidapi.com
```

When set, the backend also requires `X-RapidAPI-Host` to match this value.

Automatic provisioning is enabled by default:

```dotenv
RAPIDAPI_AUTO_PROVISION=true
```

With auto-provisioning enabled, the first valid request for a new `X-RapidAPI-User` creates:

- one `CatalogApiConsumer`;
- one `CatalogApiExternalIdentity` with `provider=rapidapi`;
- a plan name such as `rapidapi_basic` or `rapidapi_pro`.

The same RapidAPI user reuses the same consumer on later requests. Subscription changes update the linked consumer plan.

If auto-provisioning is disabled, unknown RapidAPI users receive `403 RAPIDAPI_USER_UNPROVISIONED`.

## Local quota ceilings

RapidAPI marketplace billing/quota enforcement remains the marketplace responsibility. Local ceilings therefore default to unlimited (`0`) so the backend does not accidentally disagree with the marketplace plan:

```dotenv
RAPIDAPI_BASIC_LOCAL_QUOTA=0
RAPIDAPI_PRO_LOCAL_QUOTA=0
RAPIDAPI_ULTRA_LOCAL_QUOTA=0
RAPIDAPI_MEGA_LOCAL_QUOTA=0
RAPIDAPI_CUSTOM_LOCAL_QUOTA=0
```

Set positive values only when an additional backend-side monthly ceiling is wanted.

All request counters are updated under a database row lock. A quota of `1` therefore cannot be consumed successfully by two simultaneous requests through a check-then-increment race.

For finite local quotas the response includes:

```http
X-RateLimit-Limit: 10000
X-RateLimit-Remaining: 9999
```

For an unlimited local quota (`0`), those two headers are omitted rather than reporting a misleading zero remaining.

Every successful catalog API response includes the diagnostic channel header:

```http
X-Catalog-Auth-Channel: native
```

or:

```http
X-Catalog-Auth-Channel: rapidapi
```

## Stored identity data

`catalog_api_external_identities` stores:

- provider (`rapidapi`);
- RapidAPI external user identifier;
- current subscription name;
- non-secret request metadata such as RapidAPI version and host;
- last-seen timestamp;
- linked `CatalogApiConsumer`.

The proxy secret and a customer's `X-RapidAPI-Key` are never stored in this table.

## Failure modes

| Condition | HTTP | Code |
|---|---:|---|
| Provider-side RapidAPI headers received but server secret is not configured | 503 | `RAPIDAPI_NOT_CONFIGURED` |
| Missing/wrong proxy secret | 401 | `RAPIDAPI_PROXY_INVALID` |
| Missing RapidAPI user | 401 | `RAPIDAPI_USER_REQUIRED` |
| Configured expected host does not match | 401 | `RAPIDAPI_HOST_INVALID` |
| Auto-provision disabled and identity unknown | 403 | `RAPIDAPI_USER_UNPROVISIONED` |
| Linked API consumer disabled | 403 | `API_CONSUMER_INACTIVE` |
| Positive local monthly ceiling exhausted | 429 | `QUOTA_EXCEEDED` |

## Direct API remains available

Direct customers continue to authenticate with:

```http
X-API-Key: emud_...
```

or:

```http
Authorization: Bearer emud_...
```

No RapidAPI configuration is required for direct API consumers.

## Batch endpoints and marketplace billing

`POST /api/v1/vin/batch` and `POST /api/v1/parts/by-number/batch` each cost **one** metered request whatever the batch size, because that is how RapidAPI bills and disagreeing with the marketplace's own counter causes support tickets. What bounds them is the item ceiling:

```dotenv
CATALOG_API_BATCH_MAX_ITEMS=50
```

Raise it deliberately. At 50 items a caller can pull 50 rows per billed request, which is the intended value of a paid plan; at 5,000 they can drain the catalogue through a handful of calls. If batch size should differ by plan, gate it on the resolved consumer's `plan` rather than raising the global ceiling.

## Response caching

```dotenv
CATALOG_API_CACHE_ENABLED=true
CATALOG_API_CACHE_TTL=300
```

Cache entries are shared across consumers on purpose — what may be published depends on source rights, not on the caller — and are keyed by a catalog version stamp so any change to a part, assertion or source retires them all. Quota headers are written onto the live response, so a cache hit still reports the right remaining quota for that caller. Keep it on: marketplace traffic is repetitive, and the same VIN decoded twice would otherwise cost two upstream vPIC calls.

## Before listing a plan

The transport is ready; what a plan can promise depends on what has actually been imported and cleared. Check, in this order:

1. `GET /api/v1/coverage` against production — it reports visible entity counts per source. A listing whose endpoints return empty collections earns refunds and one-star reviews.
2. Which sources are enabled. All four open profiles ship `is_active = false`, and every real supplier ships `allow_api_redistribution = false`; the parts endpoints have no licensed source behind them until one is contracted (see `MAHLE_TECCMD.md`).
3. `GET /api/v1/sources` — the licence terms a subscriber inherits. EEA is CC BY and NHTSA asks that attribution survives copying, so the listing description and the plan's terms need to pass the credit obligation on. `meta.license_notice` gives subscribers the line to reproduce.
4. Document only the endpoints that return data today. It is better to list a narrow "EU vehicle identification" API that works than a broad catalogue API with six empty endpoints.

## Deployment checklist

1. Run migrations.
2. Configure `RAPIDAPI_PROXY_SECRET` in the deployment secret store.
3. Optionally configure `RAPIDAPI_EXPECTED_HOST`.
4. Keep local RapidAPI quotas at `0` initially unless a deliberate defense-in-depth ceiling is required.
5. Configure the RapidAPI gateway/backend URL to the public eMUD `/api/v1` API.
6. Test a valid request through RapidAPI rather than manually injecting a customer's `X-RapidAPI-Key` directly at the backend.
7. Confirm a direct request without native credentials is rejected.
8. Confirm an invalid `X-RapidAPI-Proxy-Secret` is rejected even when a valid native eMUD API key is also present.
