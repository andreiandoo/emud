# Onboarding furnizor

## Date necesare

- documentația API sau fișier exemplu CSV/XML/JSON;
- identificator stabil, SKU/EAN/MPN;
- preț net/brut, monedă și TVA;
- cantitate sau stare de stoc, lead time și produse retrase;
- categorii, atribute, imagini și compatibilități auto;
- limite de trafic, autentificare și politica de refresh;
- metoda de transmitere a comenzilor și anulărilor.

## Exemplu

```php
Supplier::create([
    'name' => 'ACME 4x4',
    'code' => 'ACME',
    'protocol' => 'json',
    'catalog_endpoint' => 'https://supplier.example/api/products',
    'credentials' => ['bearer_token' => 'secret'],
    'field_mapping' => [
        'external_id' => 'id', 'name' => 'title', 'sku' => 'code', 'ean' => 'gtin',
        'cost_price' => 'pricing.net', 'stock_quantity' => 'inventory.available',
        'stock_status' => 'inventory.status', 'images' => 'media.images',
    ],
    'settings' => ['items_path' => 'data.items', 'auto_create_products' => false],
]);
```

`auto_create_products=false` este recomandat la primul import. După verificarea deduplicării prin EAN/MPN, poate fi activat; produsele noi intră în `review`, nu direct public.

## Criterii de acceptare

1. Două importuri identice nu creează duplicate.
2. Un produs dispărut este marcat după perioada convenită, nu șters.
3. O ofertă expirată nu poate fi aleasă la checkout.
4. Prețul și stocul anterior rămân în istoric.
5. Un rând invalid nu oprește tot feedul, dar este raportat.
6. Credentialele nu apar în loguri sau repository.
