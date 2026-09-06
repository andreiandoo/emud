# Catalog API — v1

The catalog API is authenticated independently from ecommerce users.

## Authentication

Send either:

```http
X-API-Key: emud_...
```

or:

```http
Authorization: Bearer emud_...
```

Keys are stored as SHA-256 hashes. The plaintext key is returned only once when issued.

## Rights enforcement

An entity is publicly searchable only when at least one published `catalog_source_assertion` for that entity is marked `api_redistributable=true`.

Fitment, number and part-relation endpoints additionally require the underlying source to have `allow_api_redistribution=true`.

Internal admin/ecommerce visibility can therefore be broader than public API visibility.

## Endpoints

```text
GET  /api/v1/vehicles/search
GET  /api/v1/vehicles/{id}
GET  /api/v1/vehicles/{id}/parts

GET  /api/v1/parts/search
GET  /api/v1/parts/by-number/{number}
GET  /api/v1/parts/resolve/{number}
GET  /api/v1/parts/{publicId}

POST /api/v1/compatibility/check
GET  /api/v1/coverage
GET  /api/v1/changes
```

### Part graph resolution

`GET /api/v1/parts/resolve/{number}` starts from an API-redistributable part identifier and walks the canonical cross-reference / supersession graph.

Optional query parameters:

```text
scheme     MPN, OE, IAM, EAN_GTIN, etc.
depth      0..5, default 2
max_nodes  1..500, default 100
max_edges  1..2000, default 250
```

Example:

```http
GET /api/v1/parts/resolve/OC%20123?scheme=MPN&depth=2
```

The response contains:

- `data.nodes[].part`: normal public part serialization
- `data.nodes[].depth`: shortest discovered hop count from the queried identifier
- `data.nodes[].path_confidence`: minimum confidence along the best discovered path
- `data.edges[]`: canonical relation edges such as `equivalent` or `superseded_by`
- `meta.truncated`: true when a caller-supplied graph limit prevented complete traversal

The traversal is breadth-first, cycle-safe, batched per depth, and follows only relation edges whose source permits API redistribution. Reverse traversal is allowed for discovery, while each edge is serialized once in its canonical source/target direction.

## API key creation (temporary CLI/Tinker workflow)

Until the API-consumer admin UI is added:

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

If a source has `settings.auto_canonicalize=true`, canonicalization is queued automatically after a successful source download.
