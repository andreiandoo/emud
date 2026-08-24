# eMUD

Magazin vehicle-first pentru 4x4, off-road și overlanding, construit pentru dropshipping cu mai mulți furnizori. Catalogul public este canonic; feedurile furnizorilor sunt păstrate separat ca surse de ofertă, stoc și cost.

## Stack

- PHP 8.4, Laravel 12, Livewire 4
- Blade, Tailwind CSS 4, Alpine.js (inclus de Livewire)
- PostgreSQL 17 cu indexare `pg_trgm` și JSONB
- Redis 8 pentru cache, sesiuni, lock-uri, cozi și scheduler
- Nginx + PHP-FPM, containere separate pentru web, worker și scheduler

## Pornire locală

```bash
cp .env.example .env
docker compose build
docker compose up -d postgres redis
docker compose run --rm app php artisan key:generate --show
```

Copiază cheia rezultată în `APP_KEY`, completează `ADMIN_EMAIL` și `ADMIN_PASSWORD`, apoi:

```bash
docker compose run --rm app php artisan migrate --seed
docker compose up -d
```

Aplicația este disponibilă la `http://localhost:8080`, iar administrarea la `/admin/login`.

## Sincronizări furnizori

```bash
php artisan suppliers:sync ACME --mode=catalog
php artisan suppliers:sync ACME --mode=stock
php artisan suppliers:sync ACME --mode=prices
```

Schedulerul rulează automat: stoc la 15 minute, prețuri în fiecare oră și catalog complet zilnic la 02:10. Fiecare furnizor are propriul mapping de câmpuri și, când este necesar, propriul connector PHP. Credentialele sunt criptate de Laravel înainte de a fi salvate în PostgreSQL.

## Principii de date

1. `products` și `product_variants` reprezintă produsul eMUD.
2. `supplier_products` păstrează copia brută și identificatorii fiecărui furnizor.
3. `supplier_offers` păstrează starea curentă a costului și stocului; `supplier_offer_history` păstrează schimbările.
4. O comandă salvează un snapshot al produsului și furnizorului ales, astfel încât istoricul să nu se schimbe retroactiv.
5. Compatibilitatea nu este un text liber: este o regulă structurată pe marcă, model, generație, motor, ani și condiții de montaj.

Detalii: [`docs/architecture.md`](docs/architecture.md), [`docs/data-model.md`](docs/data-model.md), [`docs/supplier-onboarding.md`](docs/supplier-onboarding.md).
