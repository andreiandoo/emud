# Plan de implementare — furnizori și cataloagele lor

**Data:** 2026-09-08
**Surse:** `supplier_integration_handoff_spec.md`, `automotive_dropshipping_supplier_research_eu_us_2026-09-06.md`, `automotive_4x4_offroad_supplier_strategy_2026-09-06.md`, `automotive_high_margin_product_opportunities_2026-09-06.md`
**Referință internă:** `architecture.md`, `data-model.md`, `supplier-onboarding.md`, `catalog-api.md`

---

## Jurnal de execuție

Ce s-a terminat efectiv, cu deciziile luate pe parcurs. Planul de mai jos rămâne valabil; secțiunea asta spune unde suntem în el.

### 2026-09-08 / 09 — stratul de vehicule, închis

**EEA — complet.** 223,805 înregistrări în staging, 221,847 configurații canonice, 863 mărci cu vehicule reale în spate. Importul nu se terminase niciodată înainte: paginarea cu OFFSET peste tot tabelul îl obliga pe Discodata să resorteze întregul `DISTINCT` la fiecare pagină, deci paginile încetineau cu adâncimea — 8s la pagina 1, 22s la pagina 1,099. La ~46 înregistrări/s, cele 1,53M de rânduri cereau ~7,5 ore contra unui timeout de job de 2. Împărțirea crawl-ului pe producător aduce pagina la 1-2s. Timeout-urile Discodata (HTTP 200 cu cheie `errors`, invizibile pentru retry-ul clientului HTTP) se reîncearcă acum în loc să omoare rularea.

Cele 1,53M de rânduri devin 223,805 înregistrări pentru că EEA e un registru de înmatriculări: aceeași configurație apare de zeci de ori cu măsurători diferite de CO2 și masă, iar `external_id` hash-uiește doar coloanele de identitate. **Compromis acceptat:** masa (`m (kg)`) nu e în identitate, deci se păstrează o valoare arbitrară per configurație. Irelevant pentru fitment; de reconsiderat dacă va fi nevoie de intervalul de masă.

**vPIC — complet pe modul `catalog`.** 67,348 înregistrări: 22,991 producători, 12,359 mărci, 31,998 modele, în 15 minute.

**vPIC — modurile de referință, respinse deliberat.** `model_years` cere 383,129 apeluri (12,359 mărci × 31 ani) cu lot de 5 mărci, adică 2,472 dispecerizări; `manufacturer_links` ~1,000; `vehicle_types` 248. În plus fiecare dispecerizare reface lista completă de mărci sau producători înainte să înceapă lucrul. Alternativa corectă e `catalog:vpic:install`, care restaurează dump-ul PostgreSQL oficial NHTSA. **Amânat:** `resolver_mode` e pe `http` și decodarea VIN funcționează; instalarea standalone costă câțiva GB pe un server partajat și se face când volumul o justifică.

**Wikidata — sărit deliberat.** Nu e sursă de import (nu are `connector_class`); `catalog:wikidata:enrich` îmbogățește entități existente cu alias-uri localizate și QID-uri. După vPIC ar însemna o căutare API pentru fiecare din 13,138 de mărci, majoritatea ateliere americane fără intrare în Wikidata. De reluat restrâns, dacă și când calitatea căutării o cere.

**Bug reparat, cu efect retroactiv.** `CanonicalizeCatalogSourceRecords` și `MatchSupplierProductsToCatalog` paginau cu `each()`, care e bazat pe OFFSET, în timp ce callback-ul muta înregistrările în afara statusurilor pe care query-ul filtrează. Publicarea unei pagini micșora setul cu o pagină, deci offset-ul următor sărea peste înregistrări neprocesate — iar rularea raporta „completed" după ce procesase aproximativ o pagină din două. Asta explică LIFEOFCAPO: 6,172 stageate, două rulări, 3,642 publicate. Trecut pe `chunkById`. Canonicalizarea își pune singură la coadă lotul următor, altfel o sursă mai mare decât un lot rămânea pe jumătate.

**Selectorul de vehicul.** vPIC a dus dropdown-ul din magazin de la 899 la 13,138 de mărci, din care doar 863 au vehicule. Înregistrările rămân — sunt necesare pentru VIN — dar selectorul filtrează acum ambele niveluri la ce are configurații în spate.

**`suppliers:onboarding-check`.** Comutatoarele care opresc promovarea tehnică eșuează toate în tăcere. Comanda le raportează înainte de import, plus câmpurile lipsă din `field_mapping` și listele mapate fără delimitator.

**Ce blochează acum:** nimic tehnic. Traseul furnizor → piesă canonică e implementat și testat; lipsesc datele reale de la un furnizor.

---

## 0. Punctul de plecare real

Specificația de la ChatGPT presupune că pornim de la zero. Nu e cazul. Codul are deja o bună parte din „Phase 1" cerut în secțiunea 22 a spec-ului.

### Ce există și rămâne baza

| Componentă | Unde | Stare |
|---|---|---|
| `suppliers` (cod, protocol, endpointuri, credențiale criptate, `field_mapping`, `settings`, prioritate) | `2026_08_24_000300_create_supplier_tables.php` | funcțional |
| Drepturi de date per furnizor (`allow_internal/ecommerce/derived/api_redistribution`, licență) | `2026_09_06_001100_...` | funcțional, deja folosit de Catalog API |
| `supplier_products` + `raw_payload` + `source_hash` + `last_seen_at` | idem | funcțional |
| `supplier_offers` (1:1 cu supplier_product) + `supplier_offer_history` | idem | prea sărac (vezi Etapa 2) |
| `supplier_sync_runs` + `supplier_feed_artifacts` (checksum, snapshot sursă) | `...000300`, `...001100` | funcțional |
| `supplier_sync_schedules` (cron per furnizor × mod) + `suppliers:dispatch-schedules` | `...001200`, `routes/console.php` | funcțional |
| Conectori `HttpFeedConnector` / `SftpFeedConnector`, parser CSV/TSV/XML/JSON, `SupplierRecordMapper` declarativ | `app/Suppliers/` | funcțional |
| Job `SyncSupplierFeed` (coadă `imports`, `WithoutOverlapping`, try/catch per rând, tranzacție scurtă per rând) | `app/Jobs/SyncSupplierFeed.php` | funcțional |
| Matching EAN / MPN / brand+MPN + `supplier_product_match_candidates` | `app/Catalog/Matching/SupplierCatalogPartMatcher.php` | incomplet (Etapa 3) |
| Coadă de matching manual în admin | `app/Livewire/Admin/CatalogPlatform/SupplierMatchingIndex.php` | funcțional |
| Promovare date tehnice furnizor → catalog canonic, cu gard pe drepturi | `app/Catalog/SupplierPromotion/` | funcțional |
| Teste: importer, guvernanță feed, scheduler, promovare tehnică, mapper, parser, SFTP | `tests/` | funcțional |

**Regula arhitecturală cerută de spec — produsul canonic separat de oferta comercială — este deja respectată.** `auto_create_products` e implicit `false`, produsele create din feed intră în `review`.

### Ce lipsește (lista reală de lucru)

1. **Capabilities explicite.** Există doar `protocol`. Nu știm din date dacă un furnizor suportă stoc realtime, plasare comandă, tracking, retururi, TecDoc, ACES/PIES.
2. **Termeni comerciali.** Zero câmpuri pentru dropship fee, MOV/MOQ, prag transport gratuit, blind/neutral shipping, țări permise, fereastră retur, taxe restocking, MAP, termene de plată. Fără ele nu se poate calcula contribuția și nu se poate face routing corect.
3. **`supplier_sync_errors`.** Erorile pe rând sunt înghițite în `report()` + `failed_count`. Nu putem depana o schemă de feed care s-a schimbat.
4. **Model de ofertă prea subțire.** Fără depozit, fereastră de expediere, clasă de transport, greutate/dimensiuni, oversize/hazmat, `map_price`, dropship fee la nivel de SKU, eligibilitate dropship, curs valutar / valoare în monedă de bază.
5. **Matching incomplet.** Doar EAN și MPN. Fără TecDoc article ID, fără OEM/cross-reference, fără nivel normalizat separat, fără tabel de identificatori multipli.
6. **Routing inexistent.** `App\Commerce\BestSupplierOffer` **nu este apelat nicăieri** — e cod mort, iar logica lui (status stoc → prioritate → cost) nu e landed cost.
7. **Checkout fără furnizor.** `order_items.supplier_id` și `supplier_product_id` există în migrare, dar `CheckoutService` nu le scrie niciodată. Nu există revalidare de stoc/preț la checkout.
8. **Fulfilment lipsă.** Fără `supplier_orders`, linii, expedieri, tracking. Split pe mai mulți furnizori nu e posibil azi.
9. **Conformitate/omologare absentă.** Zero câmpuri `road_legal_status`, E-mark, TÜV/ABE, RAR/CIV. Pentru poziționarea 4×4 asta e blocant, nu opțional (KITT vinde explicit produse fără E-mark/ABE/TÜV).
10. **Monitorizare.** Fără alerte pe feed căzut, credențiale expirate, prăbușire de număr de înregistrări, stoc/preț învechit.
11. **Preț public.** `RetailPriceCalculator` e markup fix global. Fără reguli per categorie/brand/furnizor, fără marjă minimă, fără respectare MAP, fără FX.
12. **XLSX.** AVEX oferă XML/CSV/XLSX; parserul nu citește XLSX.
13. **Tabele moarte.** `supplier_category_mappings` și `supplier_attribute_mappings` există în migrare din august, dar nu au model, nu au cod și nu au teste. Maparea categoriei furnizorului pe arborele nostru și a câmpurilor lui pe `attributes` se face azi manual sau deloc.

---

## 1. Principii care nu se negociază

Preluate din spec și din arhitectura existentă; orice etapă de mai jos le respectă.

1. Produsul canonic există **o singură dată**. Furnizorii aduc oferte, nu produse noi.
2. Nicio îmbinare automată la scor ambiguu. Prag actual: auto doar la `score >= 98` și diferență ≥ 5 față de al doilea candidat. Se păstrează.
3. Payload brut și checksum se păstrează pentru fiecare import.
4. Un rând invalid nu oprește feedul, dar **trebuie să fie vizibil** (aici e gaura de la punctul 3 de mai sus).
5. Fiecare import e idempotent; nu se împachetează un feed întreg într-o singură tranzacție.
6. Rețeaua rulează în joburi, niciodată în request-ul public.
7. Un furnizor picat nu strică magazinul public.
8. Prețul de achiziție nu ajunge niciodată la client.
9. Compatibilitatea ≠ legalitate rutieră. `road_legal_status` implicit `UNKNOWN`, niciodată dedus din feed.
10. Drepturile de date (`allow_ecommerce_data`) decid dacă putem afișa public textul/imaginile furnizorului. Mecanismul există deja pentru API; trebuie aplicat și în storefront.

---

## 2. Etape

Etapele 0–3 nu au nevoie de nicio credențială reală. Se pot începe imediat, în paralel cu deschiderea conturilor B2B.

### Etapa 0 — Profil comercial, capabilități și pipeline de furnizori — **LIVRATĂ**

Livrată în commitul `19bc117`. Ce există acum în cod:

- migrarea `2026_09_08_000800_add_supplier_commercial_profile.php` cu profil comercial, capabilități tri-state și câmpurile de onboarding;
- enum-urile `SupplierType`, `SupplierOnboardingStatus`, `SupplierStrategicRole`, `SupplierCapability`;
- `Supplier::can()`, `capabilities()`, `shipsTo()`, `hasConfirmedBlindFulfilment()`, scope-urile `prospects()` și `integrationReady()`;
- `SupplierProspectSeeder` cu toți cei 33 de furnizori EU și 20 US din cercetare, ca prospecți inactivi, cu scoruri, surse și întrebări nerezolvate;
- `SupplierEditor` cu secțiunile Qualification, Capabilities și Commercial terms;
- `SuppliersIndex` separat în „feeduri configurate" și „pipeline comercial", cu filtre;
- `SupplierCommercialProfileTest`.

Regula tri-state e cea care contează pe termen lung: `null` înseamnă „nu am întrebat încă", `false` înseamnă „furnizorul a spus nu". Doar `true` permite aplicației să apeleze capabilitatea. La fel, dropshipping ≠ dropshipping blind: `hasConfirmedBlindFulfilment()` cere explicit `blind_shipping = true` **și** `supplier_invoice_in_parcel = false`.

**De ce a fost prima:** fără termeni comerciali în bază nu putem calcula contribuția, nu putem face routing și nu putem decide ce furnizor merită adaptor. Documentele de cercetare conțineau deja aceste date pentru ~53 de furnizori — acum sunt în aplicație, nu în markdown.

<details>
<summary>Specificația inițială a etapei</summary>

Migrare `add_supplier_commercial_profile`:

```
suppliers:
  supplier_type            distributor|manufacturer|marketplace|aggregator|wholesaler
  status                   prospect|qualified|contracted|live|paused|rejected
  country_code, website, contact_json
  qualification_score      0-100 (din cercetare, recalculat la contractare)
  readiness_score          1-5
  strategic_role           specialist|backbone|fallback

  -- capabilități (citite de motor, deci coloane tipizate, nu json)
  supports_catalog, supports_prices, supports_stock, supports_realtime_stock
  supports_order_api, supports_tracking_api, supports_returns_api
  supports_tecdoc, supports_aces_pies

  -- termeni comerciali (citiți de landed cost / routing)
  base_currency, vat_mode
  dropship_fee, packaging_fee
  minimum_order_value, minimum_order_quantity
  free_shipping_threshold
  payment_terms_days
  blind_shipping, neutral_packaging, merchant_as_sender, supplier_invoice_in_parcel
  return_window_days, restocking_fee_percent, return_freight_payer
  map_policy, marketplace_restrictions
  allowed_countries jsonb, excluded_countries jsonb
  cutoff_time, default_dispatch_days_min, default_dispatch_days_max

  commercial_profile jsonb   -- chestionarul liber din spec §24, needitat de motor
```

Livrabile:

- `App\Enums\SupplierType`, `SupplierStatus`, `SupplierCapability`.
- `SupplierProspectSeeder` — cei 33 EU + 20 US din cercetare, ca `prospect`, cu scor, rol strategic, note și URL-uri sursă. Cercetarea devine pipeline operațional.
- `SupplierEditor` reorganizat pe taburi: **Prezentare / Credențiale / Capabilități / Termeni comerciali / Sincronizare / Drepturi de date / Chestionar**.
- `SuppliersIndex` cu status conexiune, ultima sincronizare reușită, nr. produse, matched/unmatched, oferte active, erori.

**Definition of done:** pot deschide adminul și văd toți furnizorii candidați cu scor și cu ce ne lipsește de la fiecare, fără să deschid documentele.

</details>

---

### Etapa 1 — Observabilitate și import defensiv — **LIVRATĂ**

Ce există acum:

- `supplier_sync_errors` (tip, mesaj, identificator extern, payload brut trunchiat la 8 KB) + `SupplierSyncErrorType`, plafonat la 500 de rânduri pe rulare;
- `supplier_sync_runs` cu `received_count`, `rejected_count`, `retired_count` și un `summary` structurat (contoare, erori pe tip, rată de eroare, rezultatul gărzii și al retragerii);
- **rândurile respinse nu mai dispar**: conectorii implementează `ReportsFeedIssues`, deci un rând fără identificator sau denumire — cazul cel mai frecvent când un furnizor redenumește o coloană — ajunge în jurnal, nu în neant;
- `SupplierFeedGuard`: dacă feedul returnează sub 70% din ultima rulare reușită, rularea intră în `aborted_guard`. Datele importate se păstrează, dar nimic nu se retrage și furnizorul nu e marcat sincronizat cu succes. Garda nu se declanșează fără o linie de bază de cel puțin 100 de rânduri, ca să nu blocheze onboardingul;
- `SupplierCatalogRetirement`: produsul absent din feed peste 7 zile primește `discontinued_at`, iar oferta trece pe `discontinued` cu stoc 0. Niciodată ștergere. Rulează doar pe `catalog` — un feed de stoc listează prin definiție un subset;
- `SupportsConnectionTest` pe ambii conectori + buton „Testează conexiunea" în admin;
- `SupplierHealthInspector` + `suppliers:health-check --notify`, rulat orar, cu deduplicare de o zi pe semnătura problemei;
- contoarele nu mai fac un `UPDATE` pe rând, ci se scriu la fiecare 200 de rânduri.

**Definition of done:** un feed cu 5 rânduri stricate din 50.000 se importă, iar în admin văd exact care 5 și de ce. ✔

---

### Etapa 2 — Model complet de ofertă, FX și cost real — **LIVRATĂ**

Ce există acum:

- `supplier_offers` extins: depozit, cost brut, `map_price`/`msrp`, taxă dropship și manipulare per ofertă, estimare transport, `pack_quantity`, fereastră de expediere, clasă de transport, greutate/dimensiuni, flaguri oversize/hazmat, eligibilitate dropship tri-state, `is_active`, tipul sursei și `source_seller_ref`/`source_seller_name` pentru marketplace-uri;
- `supplier_warehouses` și `supplier_stock_history` (scris doar la schimbare, ca un feed la 15 minute să nu adauge patru rânduri identice pe oră per SKU);
- `exchange_rates` + `CurrencyConverter`: prețul original al furnizorului nu se suprascrie niciodată; se adaugă valoarea în moneda de bază, cursul și ziua din care vine. Lipsa unui curs înseamnă „nu pot compara", **niciodată** curs 1. Cursurile inverse și încrucișate se derivă (BNR publică doar față de RON);
- `ExchangeRateImporter` + `exchange-rates:fetch`, rulat zilnic la 01:30, înainte de sincronizarea de catalog. Respectă `multiplier` (HUF e cotat la 100);
- `LandedCostCalculator`: cost produs + taxă dropship + manipulare + transport, cu taxele per comandă împărțite la cantitate. Un cost incomplet se declară incomplet, cu lista câmpurilor lipsă, în loc să pară o cifră decisă;
- `ContributionMarginCalculator`: venit net minus cost aterizat, comision de plată, rezervă de retur și de garanție. Clasele voluminoase primesc un multiplicator de transport retur, iar taxa de restocare a furnizorului intră în calcul;
- pagina de admin **Economia ofertelor**, care închide criteriul etapei.

**De ce contează multiplicatorul:** o bară de protecție cu 40% marjă brută și 120 lei transport retur poate contribui mai puțin decât un panou de comutatoare cu 50% care se expediază într-un plic. Marja brută flatează un catalog de dropshipping.

<details>
<summary>Specificația inițială a etapei</summary>

Migrare `expand_supplier_offers` + `create_supplier_warehouses`:

```
supplier_warehouses: supplier_id, code, name, country_code, cutoff_time

supplier_offers (adăugări):
  supplier_warehouse_id, warehouse_code
  cost_net, cost_gross, map_price, msrp
  cost_base_currency, fx_rate, fx_rate_at      -- originalul NU se suprascrie
  dropship_fee, handling_fee, pack_quantity
  dispatch_days_min, dispatch_days_max
  shipping_class   SMALL_PARCEL|MEDIUM|HEAVY|OVERSIZE|TYRE|WHEEL|PALLET|LTL|HAZARDOUS
  weight_kg, packed_weight_kg, length_cm, width_cm, height_cm
  oversize_flag, hazmat_flag
  is_dropship_eligible, is_active
  source_type   api|sftp|ftp|csv|xml|portal
  source_updated_at, last_verified_at
  source_seller_ref, source_seller_name        -- pentru marketplace (ALZURA/Tyre100)
```

Plus `supplier_stock_history` (opțional acum, obligatoriu înainte de scorul de fiabilitate).

Servicii noi:

- `exchange_rates` + `CurrencyConverter` (BNR/ECB, zilnic). Furnizorii cotează EUR/PLN/CZK/USD; comparația între oferte fără FX e falsă.
- `LandedCostCalculator`:
  `cost_net × fx + dropship_fee + handling_fee + freight_estimate(shipping_class, destinație)`.
- `ContributionMarginCalculator`: minus comision plată, minus rezervă retur, minus rezervă garanție, minus subvenție transport. Formula din `high_margin_product_opportunities` §2.2 și §11.
- `SupplierRecord` DTO și `SupplierRecordMapper` extinse cu noile chei (rămâne mapare declarativă, fără cod per furnizor).

**Definition of done:** pentru orice ofertă pot afișa în admin cost aterizat în RON și contribuția estimată la un preț de vânzare dat. ✔

</details>

---

### Etapa 3 — Scara de matching completă ← **următoarea**

Migrare `create_supplier_product_identifiers`:

```
supplier_product_identifiers:
  supplier_product_id, type, value, normalized_value
  type ∈ EAN|GTIN|UPC|MPN|OEM|IAM|TECDOC_ARTICLE_ID|KTYPE|SUPPLIER_XREF
  unique(supplier_product_id, type, normalized_value)
```

`SupplierCatalogPartMatcher` extins pe nivelurile din spec §3:

| Nivel | Criteriu | Scor |
|---|---|---|
| 1 | TecDoc article ID | 100 |
| 1 | EAN/GTIN/UPC (există) | 100 |
| 1 | brand + MPN exact (există) | 98 |
| 2 | referință OEM / cross-reference / supersession | 92 |
| 3 | brand normalizat (cu tabel de aliasuri) + MPN normalizat | 86 |
| 4 | orice sub prag → coadă manuală, niciodată auto | — |

- Tabel de aliasuri de brand (`brand_aliases`) — „Bosch" / „BOSCH" / „Robert Bosch GmbH".
- Reguli de mapare persistente: o confirmare manuală creează regulă reutilizabilă la următorul import (`CatalogMappingRule` există deja — de reutilizat, nu de duplicat).
- Metrici de calitate per rulare (spec §15): matched_by_ean / mpn / tecdoc / oem, identificatori în conflict, prețuri invalide, lipsă imagini, lipsă fitment.
- `SupplierMatchingIndex` primit: comparație lângă lângă a înregistrării furnizorului cu candidații, filtrare pe furnizor/motiv/scor, acțiune în masă.

**Definition of done:** import repetat de două ori pe același feed → zero produse canonice duplicate, aceleași mapări, coada manuală nu crește.

---

### Etapa 4 — Routing, preț public și checkout

Aici se leagă subsistemul de magazin. Azi legătura nu există.

- `SupplierOfferRouter` înlocuiește `BestSupplierOffer` (cod mort). Returnează oferte **scorate**, nu doar cea mai ieftină:

```
supplier_score =
    landed_cost_score
  + stock_confidence_score        (exact vs. bucket vs. necunoscut, vechime)
  + dispatch_score
  + reliability_score             (fill rate, rată anulare — implicit neutru până există date)
  + dropship_branding_score       (blind/neutral)
  + return_economics_score
  + strategic_priority
  − split_order_penalty
```

- Filtre dure înainte de scor: furnizor activ, ofertă nu e stale, `is_dropship_eligible`, țara destinație permisă, MOV atins sau acceptat.
- `pricing_rules` (categorie / brand / furnizor / clasă transport): markup, marjă minimă, rotunjire, respectare MAP. `RetailPriceCalculator` devine consumatorul acestor reguli.
- Storefront: prețul și disponibilitatea produsului vin din oferta câștigătoare, cu cache 5–15 min pentru navigare.
- Checkout (spec §9): revalidare **live** stoc + preț înainte de plată pentru furnizorii cu `supports_realtime_stock`; dacă s-a schimbat material, rerutare pe alt furnizor eligibil sau blocare linie.
- `CheckoutService` scrie `order_items.supplier_id`, `supplier_product_id` și un snapshot de cost. Coloanele există; doar nu sunt populate.

**Definition of done:** o comandă de test are pe fiecare linie furnizorul ales, costul la momentul comenzii și motivul alegerii.

---

### Etapa 5 — Fulfilment multi-furnizor

Migrare `create_supplier_fulfilment_tables`:

```
supplier_orders          customer_order_id, supplier_id, external_order_id, status,
                         currency, products_cost, shipping_cost, total_cost,
                         ship_to_*, placed_at, confirmed_at, shipped_at, cancelled_at,
                         raw_response_json
supplier_order_lines     supplier_order_id, order_item_id, supplier_product_id, qty, unit_cost
supplier_shipments       supplier_order_id, carrier, tracking_number, shipped_at
supplier_tracking_events supplier_shipment_id, status, description, occurred_at
```

- `SupplierOrderSplitter`: comandă client → grupuri de fulfilment → PO per furnizor.
- **Mod manual întâi:** adminul vede PO-ul generat, îl plasează în portalul furnizorului, marchează plasat, lipește AWB. Funcționează din prima zi de comerț real, fără API.
- **Mod automat după:** pentru furnizorii cu `supports_order_api`, aceleași entități se populează prin adaptor.
- Polling tracking la 30–60 min pentru comenzi deschise.

---

### Etapa 6 — Adaptoare reale, în ordinea din cercetare

Contractul rămâne cel existent (`SupplierConnector::records()`), extins cu interfețe opționale, ca să nu rescriem ce funcționează:

```php
interface SupportsConnectionTest   { public function testConnection(Supplier $s): SupplierConnectionResult; }
interface SupportsRealtimeStock    { public function checkAvailability(Supplier $s, array $skus): Collection; }
interface SupportsOrderPlacement   { public function createOrder(SupplierOrderRequest $r): SupplierOrderResult; }
interface SupportsOrderTracking    { public function getTracking(Supplier $s, string $externalOrderId): SupplierTrackingResult; }
interface SupportsReturns          { public function requestReturn(ReturnRequest $r): SupplierReturn; }
```

`ConnectorRegistry` verifică `supports_*` din DB înainte de a chema o capabilitate. Aplicația nu presupune niciodată același nivel de integrare.

Ordinea de implementare (din `4x4_offroad_supplier_strategy` §17 și §22):

| # | Furnizor | Transport | Ce demonstrează | Prerechizite |
|---|---|---|---|---|
| 0 | **Mock/Demo** | fixtures locale | contractul comun, teste de idempotență | niciuna |
| 1 | **AVEX** (RO) | XML/CSV/**XLSX** orar | ingestie feed + matching pe furnizor real RO | cont B2B + **parser XLSX de adăugat** |
| 2 | **eHornet** (CEE) | REST + CSV/XML | primul REST, sync zilnic preț/stoc | cont partener |
| 3 | **TASY** (CZ) | API sau FTP XML | **comenzi + tracking + facturi XML** — primul flux complet | cont + doc API |
| 4 | **KITT** (RO) | CSV/XML | catalog vehicle-specific + **normalizare conformitate** | cont B2B, Etapa 7 |
| 5 | **ViTire** (PL) | XML | atribute anvelope off-road (AT/MT/RT, 3PMSF) | cont B2B |
| 6 | **Fahrzeugbeleuchtung** (DE) | CSV/XML/API | iluminat, expediere neutră, clasificare E-mark | cont B2B |
| 7 | **VTVAuto** (SK) | XML | protecție/accesorii vehicle-specific | cont B2B |
| 8 | **ALZURA/Tyre24** (DE) | API + CSV până la 24×/zi | **fallback de disponibilitate**, `distributorList` = mai multe oferte per articol | cont Prime + pachet API plătit |

Note tehnice pe adaptoare:

- **AVEX** cere XLSX în `StructuredSupplierFeedParser`. Feed separat „produse noi" → sync incremental mai des decât cel complet.
- **ALZURA** e marketplace: un articol are oferte de la 2000+ sub-furnizori. Se modelează ca **un** furnizor cu `source_seller_ref`/`source_seller_name` pe ofertă (adăugate în Etapa 2). Nu se creează 2000 de furnizori.
- **TASY** e cel mai bun candidat pentru a valida capabilitățile de comandă înainte să investim în ele pentru toți.
- Furnizorii US rămân `prospect`. Pentru lansarea RO, economia transportului transatlantic, TVA la import și retururile le fac neviabile ca sursă directă. Rămân relevanți pentru un magazin US ulterior și ca informație de piață (ce branduri să căutăm la distribuitorii lor EU).

---

### Etapa 7 — Conformitate, fitment 4×4 și sortiment curat

Rulează **în paralel** cu 4 și 6; blochează publicarea produselor KITT/iluminat.

**Conformitate** (migrare pe `products` / `catalog_parts`, valori implicite conservatoare):

```
road_legal_status      UNKNOWN|ROAD_LEGAL|CONDITIONAL|OFFROAD_ONLY   (implicit UNKNOWN)
approval_type          E_MARK|ECE|EU_TYPE_APPROVAL|TUV|ABE|RAR_CERTIFICATE|NONE
approval_number, approval_document_url
road_legal_countries jsonb
requires_rar_inspection, requires_civ_update
requires_authorized_installer, installation_certificate_required
professional_installation_recommended
```

Regulă de import: niciun feed nu poate seta `ROAD_LEGAL`. Doar un operator, pe bază de document. Fișa de produs afișează explicit „Compatibil: DA / Legal pe drum public: DE VERIFICAT".

**Atribute 4×4** (în `attributes` + `attribute_category`, mapate prin `supplier_attribute_mappings` care există deja și e nefolosit):

- Roți/anvelope: PCD, ET, centre bore, lățime jantă, diametru, dimensiune anvelopă, indice sarcină/viteză, tip teren AT/MT/RT, 3PMSF.
- Suspensie: `lift_mm`, punte, rată arc, lungime amortizor, necesită corecție geometrie.
- Recuperare: capacitate nominală, WLL, sarcină de rupere, lungime/material cablu.
- Iluminat: lumeni, putere, tensiune, tip fascicul, IP, E-mark.
- Transport: greutate/dimensiuni ambalat, clasă oversize, necesită palet.

**Dependențe de montaj** — `CatalogFitmentConstraint` există; de extins cu reguli de tip `requires_winch_plate`, `not_with_360_camera`, `requires_drilling`, `requires_geometry_correction`.

**Mapare categorii și atribute furnizor** — `supplier_category_mappings` și `supplier_attribute_mappings` sunt tabele goale fără cod. Aici primesc model, UI de mapare în tabul furnizorului (categorie externă → categorie internă, câmp extern → atribut tipizat cu transformare) și sunt consumate de importer. Fără ele, fiecare adaptor nou ar cere cod PHP pentru taxonomie.

**Sortiment curat:** catalogul canonic poate ingera milioane de rânduri, dar lansarea comercială are **500–1.500 SKU curatoriate** (10–15 familii de vehicule × 5–8 intenții × 3–10 oferte). Nevoie de flag `is_curated` și de produse-bundle cu BOM, unde disponibilitatea se calculează din componente. Mixul propus (`high_margin` §13): 15% iluminat/electric, 15% recuperare, 12% protecție, 12% suspensie, 10% portbagaj/cargo, 10% praguri/exterior, 8% anvelope, 6% jante, 5% trolii, 4% interior, 3% restul.

---

## 3. Traseul comercial (paralel cu codul, nu după)

**Stare la 2026-09-08: niciun cont B2B deschis la niciun furnizor.** Etapele 0–5 și 7 nu depind de asta. Etapa 6 este blocată integral până la primul cont aprobat, deci traseul de mai jos e drumul critic al proiectului, nu o activitate secundară.

### 3.1 Prerechizite pentru orice cerere B2B

Aproape toți furnizorii din cercetare cer aceleași lucruri înainte să dea acces la preț de gros și la feed. De verificat ce avem înainte de a trimite prima cerere:

- persoană juridică înregistrată (SRL) + CUI;
- **cod de TVA valid în VIES** — obligatoriu pentru reverse-charge la achiziții intra-comunitare (DE/PL/CZ/SK). Fără el, furnizorii EU facturează cu TVA local și economia se schimbă;
- site funcțional sau cel puțin demonstrabil — mai mulți furnizori verifică manual activitatea comercială înainte de aprobare (Fahrzeugbeleuchtung declară verificare manuală a firmei; furnizorii US cer explicit magazin profesional);
- email pe domeniu propriu și telefon de firmă;
- extras ONRC / certificat de înregistrare, la cerere;
- metodă de plată: AVEX descrie portofel prepaid pentru dropship; restul cer transfer bancar, eventual credit după istoric.

Cel mai mic prag de intrare îl au **AVEX și KITT** — ambele românești, deci fără complicații de TVA intra-comunitar și fără barieră de limbă. Acestea două rămân primele două contacte.

### 3.2 Ordinea de contactare

**AVEX → KITT → eHornet → ViTire → TASY → Fahrzeugbeleuchtung → VTVAuto → ALZURA.**

ALZURA rămâne ultimul intenționat: contul are cost lunar și pachet API plătit, deci nu se deschide înainte să existe trafic real.

### 3.3 Ce cerem la fiecare contact

1. Pachetul din `4x4_offroad_supplier_strategy` §20: comercial, fulfilment, retur/garanție, tehnic, specific 4×4.
2. **Întrebarea eliminatorie, înaintea oricărei linii de cod:** *livrați direct către clientul nostru final din România, cu colet neutru și fără factura voastră în pachet?* RIDEX, Inter Cars, Deldo și Auto Partner sunt marcate explicit „de confirmat" în cercetare — un API excelent nu înseamnă dropshipping.
3. **Mostră de feed** (200–500 rânduri) și documentație. Mostra se poate cere adesea înainte de aprobarea contului și e suficientă pentru a construi și testa adaptorul.
4. Test de 100 SKU: cost aterizat real → preț de vânzare realist → contribuție → contribuție ajustată cu retururi. Abia apoi se decide adaptorul.

### 3.4 Urmărirea în aplicație

Pipeline-ul de furnizori din Etapa 0 devine, până la primul contract, **instrumentul principal de lucru**. Câmpul de status urmărește procesul, nu doar rezultatul:

```
onboarding_status:
  not_started → contacted → application_sent → docs_requested
             → sample_received → approved → contracted → live
             | rejected | on_hold
onboarding_notes, contacted_at, next_action, next_action_due_at
```

Rezultatele testului de 100 SKU actualizează `qualification_score`, nu un document separat.

### 3.5 Consecință asupra dezvoltării

Fără feed real, **adaptorul mock cu fixtures nu mai e un artefact de test, ci mediul principal de dezvoltare** pentru Etapele 1–5. Fixture-urile se construiesc după structurile publice documentate în cercetare (XML/CSV AVEX, REST eHornet, XML TASY) și se înlocuiesc cu mostre reale imediat ce sosesc.

---

## 4. Ordinea de execuție și dependențe

```
Etapa 0 ─┬─> Etapa 1 ─┬─> Etapa 2 ──> Etapa 4 ──> Etapa 5
         │            │
         └─> Etapa 3 ─┘                    Etapa 6 (după 2 și 3, per furnizor)
                                           Etapa 7 (paralel; blochează publicarea)
```

Estimare de efort, orientativă:

| Etapă | Mărime |
|---|---|
| 0 — profil comercial + seeder prospecți + admin | **livrată** |
| 1 — erori, gardă, sănătate, alerte | **livrată** |
| 2 — ofertă completă, FX, landed cost | **livrată** |
| 3 — scara de matching + identificatori | medie |
| 4 — routing, prețuri, checkout | medie-mare |
| 5 — fulfilment multi-furnizor | mare |
| 6 — per adaptor | mică fiecare, după ce 0–3 sunt gata |
| 7 — conformitate + atribute 4×4 | medie |

---

## 5. Riscuri și decizii de luat

| Subiect | Recomandare |
|---|---|
| `BestSupplierOffer` e cod mort și naiv | De înlocuit în Etapa 4, nu de extins. |
| ALZURA = marketplace cu 2000 sub-furnizori | Un singur `supplier`, sub-vânzătorul pe ofertă. Altfel explodează tabela de furnizori. |
| Crearea automată de produse canonice din feed | Rămâne **oprită**. Curatorierea e avantajul competitiv, nu numărul de SKU. |
| Drepturi de date pentru text/imagini furnizor | Mecanismul există (`allow_ecommerce_data`) dar e aplicat doar în Catalog API. De aplicat și în storefront înainte de a publica conținut de furnizor. |
| Transport voluminos (bare, praguri, jante, anvelope) | Free shipping doar când contribuția coșului îl suportă. Fără asta, produsele cu marjă 40% ies pe pierdere. |
| Furnizori US | Doar `prospect` + informație de piață pentru lansarea RO. |
| Anvelope/jante | Ancoră de trafic și AOV, nu sursă de marjă. Marja vine din bundle-uri (TPMS, prezoane, inele centrare, kit reparație). |

---

## 6. Ce se poate începe azi, fără nicio credențială

Cu zero conturi deschise, asta e tot ce rămâne pe masă — și e mult:

- ~~Etapa 0~~ — livrată în `19bc117`.
- ~~Etapa 1~~ — livrată.
- ~~Etapa 2~~ — livrată.
- Etapa 3 integral, testat pe adaptorul mock cu fixtures.
- Etapa 4 integral, cu oferte generate de mock — routingul și revalidarea la checkout nu au nevoie de furnizor real ca să fie corecte.
- Etapa 5 în modul manual (PO generat, plasat de operator) — funcționează chiar și fără niciun API de furnizor.
- Etapa 7, partea de schemă: câmpuri de conformitate + set de atribute 4×4 + mapare categorii/atribute furnizor.
- Parser XLSX (necesar oricum pentru AVEX).
- Adaptorul mock + fixtures modelate după structurile publice AVEX/eHornet/TASY.

Singurul lucru care **nu** se poate face este Etapa 6. Adică: subsistemul poate fi complet, testat și executabil înainte să existe primul contract, exact cum cere spec-ul în §22.
