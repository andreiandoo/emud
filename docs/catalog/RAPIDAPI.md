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

## Deployment checklist

1. Run migrations.
2. Configure `RAPIDAPI_PROXY_SECRET` in the deployment secret store.
3. Optionally configure `RAPIDAPI_EXPECTED_HOST`.
4. Keep local RapidAPI quotas at `0` initially unless a deliberate defense-in-depth ceiling is required.
5. Configure the RapidAPI gateway/backend URL to the public eMUD `/api/v1` API.
6. Test a valid request through RapidAPI rather than manually injecting a customer's `X-RapidAPI-Key` directly at the backend.
7. Confirm a direct request without native credentials is rejected.
8. Confirm an invalid `X-RapidAPI-Proxy-Secret` is rejected even when a valid native eMUD API key is also present.
