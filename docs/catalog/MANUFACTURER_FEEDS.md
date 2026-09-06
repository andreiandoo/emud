# Generic manufacturer feed contract

For structured aftermarket/OEM manufacturer feeds, configure the source with:

```text
canonicalizer_class = App\Catalog\Canonicalization\Parts\ManufacturerPartCanonicalizer
```

The source's `field_mapping` maps canonical keys to source paths.

Example:

```json
{
  "external_id": "article.id",
  "record_type": "type",
  "brand": "article.brand",
  "mpn": "article.number",
  "name": "article.name",
  "description": "article.description",
  "category": "article.genericArticle",
  "ean": "article.ean",
  "oe_numbers": "article.oeReferences",
  "iam_numbers": "article.interchanges",
  "attributes": "article.attributes",
  "fitments": "article.applications"
}
```

## OE/IAM number representation

Accepted forms:

```json
["LR073669", "LR011279"]
```

or:

```json
[
  {"number":"LR073669", "make":"Land Rover", "scheme":"OE"},
  {"number":"8K0615301", "make":"Audi", "scheme":"OE"}
]
```

## Attributes

Attributes are keyed by existing eMUD attribute `code` values. Unknown attribute codes are preserved in raw source records but are not silently added to the canonical taxonomy.

```json
{
  "diameter_mm": 320,
  "thickness_mm": 30
}
```

## Fitments

Fitments should preferably reference an already-known canonical external vehicle identifier rather than free-text make/model data.

```json
[
  {
    "id": "source-application-123",
    "vehicle_identifier_scheme": "TECDOC_KTYPE",
    "vehicle_identifier": "12345",
    "position": "front",
    "status": "confirmed",
    "confidence": 100,
    "constraints": [
      {"type":"brake_system", "operator":"=", "value":"ATE", "display_text":"For ATE brake system"}
    ]
  }
]
```

A source can alternatively pass `configuration_id` when an adapter already resolved the source vehicle to an eMUD canonical configuration.

Unresolved manufacturer fitments are **not guessed**. They create QA conflicts and remain visible in the source raw-record workbench until vehicle mapping exists.

## Categories

Preferred method: create a `catalog_mapping_rules` entry with:

```text
entity_type = category
source_value = source category name/id
target = {"category_id": 123}
```

The canonicalizer will otherwise try an exact case-insensitive match against category `full_path` or `name`. It never silently creates a new category tree from manufacturer input.

## Rights

The source rights flags control downstream exposure. A high-confidence manufacturer feed can remain internal/ecommerce-only while API redistribution is disabled. Numbers and identifiers are serialized to the public API only when their own source permits API redistribution.
