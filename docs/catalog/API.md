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

Fitment and number endpoints additionally require the underlying source to have `allow_api_redistribution=true`.

Internal admin/ecommerce visibility can therefore be broader than public API visibility.

## Endpoints

```text
GET  /api/v1/vehicles/search
GET  /api/v1/vehicles/{id}
GET  /api/v1/vehicles/{id}/parts

GET  /api/v1/parts/search
GET  /api/v1/parts/by-number/{number}
GET  /api/v1/parts/{publicId}

POST /api/v1/compatibility/check
GET  /api/v1/coverage
GET  /api/v1/changes
```

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
