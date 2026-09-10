# Architecture

All code lives under `app/Workshops/` (sources, normalisation, matching, classification, search,
export), with models in `app/Models/Workshop*`, jobs in `app/Jobs`, commands in
`app/Console/Commands`, admin pages in `app/Livewire/Admin/Workshops`, and configuration in
`config/workshops.php`. The schema is one migration:
`database/migrations/2026_09_11_000200_create_workshop_registry_tables.php`.

## Two layers

```
 source ──fetch──▶ workshop_source_records (raw, hashed, never deleted)
                        │ normalise (idempotent, re-runnable without fetching)
                        ▼
 workshop_companies ◀── workshops ──▶ contacts, authorisations → activities,
                                      services (per evidence type), capabilities,
                                      source links, website candidates
```

### Raw layer

| Table | Holds |
| --- | --- |
| `workshop_data_sources` | One row per source (`rar_service`, `rar_itp`, `rar_gpl`, `rar_tlv`, `rar_modifications`, `onrc`, `osm`, `website`, …): enabled, **cleared for public output**, configuration, state (discovered counties, last ONRC release, last OSM checksum). Created on first use from `DataSourceCatalog`; never overwritten by code afterwards. |
| `workshop_source_records` | Every record as received: `payload` (JSON) and/or `raw_content` (HTML), SHA-256 `content_hash`, `first_seen_at` / `last_seen_at` / `content_changed_at`, `parse_status` + `parse_error`, `is_current`. Unique on (source, record type, external id). `identity_key` holds what stays the same across a record's revisions (a RAR audit file or ITP station). |
| `workshop_import_runs` | One row per import: status, counters (discovered, fetched, created, updated, unchanged, failed, skipped, retired), scope, metadata (partitions planned / completed / failed, retirement guard decisions, file checksums). |

`SourceRecordStore` upserts: an unchanged hash only moves `last_seen_at`; a changed hash rewrites
the content and queues the record for parsing; a record seen twice in one run counts as a
duplicate. A record the source stops returning is **retired** (`is_current = false`), never deleted.

### Domain layer

| Table | Notes |
| --- | --- |
| `workshop_companies` | CUI (nullable, unique), legal name and normalised name, legal form, registration number, EUID, ONRC status, registered office, CAEN codes, `discovered_via` (rar / onrc), `onrc_verified_at`. |
| `workshops` | Point of work: address, locality, county, postal code, coordinates with **source and confidence**, geocode status, RAR flags (service, ITP, GPL/GNC, tachograph, B4, dismantling, mobile), capability flags (`supports_4x4`, `supports_ev`, `supports_hybrid`, `supports_trucks`, `offroad_score`), `confidence_score`, website status, `merged_into_id`. |
| `workshop_source_links` | Which source record built which workshop, how it was matched and how confidently. One record decides one workshop. |
| `workshop_contacts` | Phone, mobile, email, website, facebook, instagram, whatsapp — one row **per source per value**, with source record, source URL, confidence, primary flag, first/last seen. |
| `workshop_authorizations` | One RAR authorisation document: system (SERVICE, ITP, GPL, TLV, B4), number, exit number, audit file, station code, class, validity, current or not, the document summary. |
| `workshop_authorization_activities` | Every activity code **exactly as RAR sends it** (`A1_2_1_3`), its printed form (`A1.2.1.3`), parent, entry heading, depth, RAR's own wording, vehicle categories, limitations, restrictions (ITP interdictions, suspensions in force), observations, raw entry. B4 components become their own rows (`B4_1_3_C40`). |
| `workshop_service_types` | Our taxonomy (57 keys, e.g. `automatic_transmission`, `transfer_case`, `4x4_drivetrain`, `offroad_suspension`, `winch_installation`), independent of any source; created from `ServiceTaxonomy`. |
| `workshop_services` | Workshop × service × **evidence type** (`rar_authorization`, `website`, `osm`, `manual`, `inferred`) with evidence (codes, snippets, tags) and confidence. `is_authorized` is true only for RAR. |
| `workshop_capabilities` | `4x4`, `awd_permanent`, `ev`, `hybrid`, `trucks`, `itp_4x4`, `offroad`: value (true / false / unknown), score, basis, evidence. |
| `workshop_record_matches` | Decisions for ONRC and OSM records: matched / probable / ambiguous / unmatched / rejected, with candidates and who reviewed them. |
| `workshop_match_candidates` | Pairs of workshops that may be one place: pending, auto-merged, confirmed, rejected. |
| `workshop_website_candidates` | A possible official website: how it was found, confidence, validation evidence, crawl date. |

## Pipeline per source

**RAR** (`Sources/Rar`). `RarRegistrySource` discovers the county list from the registry and pages
through each county (`from` inclusive, `to` exclusive). `RarImporter` plans a run (or resumes the
last unfinished one), imports a county at a time — inline with `--sync`, or one `FetchRarCounty`
job per county in a batch — stores each authorisation, and normalises the changed ones at once.
After a complete, unlimited county pass it retires what the county no longer lists, unless the
county returned less than 60 % of what it held (`retire_guard_ratio`), which is treated as a
truncated answer. `RarAuthorizationParser` reads a document; `RarRecordNormalizer` writes it:

1. company by CUI (by exact normalised name in the county when there is none, and only if exactly one fits);
2. workshop: the one this record is linked to, else the one an earlier revision of the same audit file / ITP station is linked to, else a workshop of the same company **at the same address** from any RAR section (an ITP station inside a service), else a new one;
3. contacts, the authorisation and its activities; then `WorkshopStateRefresher` recomputes flags, RAR services, capabilities, primary contacts, active state and confidence.

**ONRC** (`Sources/Onrc`). The newest release is found through data.gov.ro's CKAN API; the files
are streamed (`CaretSeparatedReader`, four passes, nothing loaded whole); kept are operating
companies authorised for 4520 (CAEN Rev. 2) or 9531 (Rev. 3) and every company RAR knows. Matching
is deterministic: CUI → match; otherwise exact normalised name in the county → probable (review) or
ambiguous; an unmatched operating repair company becomes a lead company **without a workshop**.

**OpenStreetMap** (`Sources/Osm`). Geofabrik's extract is downloaded only when its md5 changes;
`osmium tags-filter` keeps workshop tags, `osmium export` writes GeoJSON sequences; each feature is
a record (`node/123`, `way/456`) with every tag. `OsmWorkshopMatcher` scores candidates found by
phone, website, a 1.5 km box and the locality; a match needs an identity signal (phone, website,
similar name or same address), a score ≥ 70 and a clear winner. Proximity alone never matches. An
unmatched named point becomes a new workshop; probable and ambiguous ones wait for review.

**Websites** (`Web`). Candidates come from OSM tags, the ONRC WEB field, business email domains
and, if configured, a search API (Brave). A candidate is accepted when its page carries the
workshop's phone, CUI, name, town or street; listing and social sites are never accepted. The
crawler reads the home page and the contact / about / services pages it links to (same host,
`robots.txt`, delay per host, page and size caps), keeps each page as a source record, extracts
contacts (generic or site-domain emails, published mailto links; not personal addresses in text),
and `KeywordServiceClassifier` turns the text into *declared* services with snippets.

## Matching and deduplication

`WorkshopDeduplicator` compares only workshops that share a phone, a website, a distinctive name
word in one locality, an address fingerprint or a 200 m cell. `WorkshopPairScorer` adds phone,
website, name, address, locality, distance and company. It **auto-merges** (score ≥ 85) only with
an identity signal and a compatible place, and never two workshops that each hold a RAR
authorisation, or belong to one company, at different addresses; such pairs are not even queued.
A likely pair (≥ 55) waits in the review queue; a decision a person took is never reopened.
`WorkshopMerger` moves links, authorisations, contacts, services and website candidates to the
survivor, keeps the duplicate (inactive, `merged_into_id`), and records the decision.

## Confidence

| Evidence | Weight |
| --- | --- |
| Current RAR authorisation | workshop 85; RAR contacts 70–85; RAR services 70–100 by how directly the code names them |
| ONRC-confirmed identity | +5, or −30 when ONRC reports dissolution, liquidation, insolvency or striking off |
| OSM point | 85 (node) / 75 (outline) for coordinates; workshops known only from OSM start at 60 |
| RAR coordinates | 60 (≥ 4 decimals), 45 (3), 25 (2 decimals = town level); none when implausible for the county or copied from a registered office elsewhere |
| Website | contacts 60–85 by kind; services 55–95 by phrase, raised when repeated |

## Capabilities

Only from evidence; a company name counts for nothing.

- **4x4**: RAR permanent all-wheel-drive transmission codes (A1.2.1.3 / A1.2.2.3) → 85; transmissions "cu tracțiune pe o axă sau pe mai multe axe" → 65; declared 4x4 / transfer-case work → 60; RAR transmission work without any multi-axle code → false.
- **EV / hybrid**: A1.1.3 / A1.1.2; a B4 EV conversion (C71); declared work. RAR had granted A1.1.2 to no workshop at all on 2026-09-10, so for hybrids the absence of the code is *unknown*, not *no*.
- **Trucks**: N2 / N3 / M2 / M3 on repair activities.
- **ITP 4x4**: an ITP station of class II/III without the "tracțiune integrală permanentă" interdiction.
- **Off-road score** (0–100): drivetrain, rigid axles, suspension, steering, geometry, chassis work, then the strong signals: RAR B4 permission for off-road equipment (C9 bull bar, C40/C82 winch, C48 roll cage, C56 suspension tuning, C25 hardtop, C30 roof rack, C65 spare-wheel carrier…) and off-road services declared on the website. `offroad = true` needs ≥ 60 and at least one strong signal.

## Jobs and queues

Every job runs on the `workshops` queue (listed in `CatalogSystemCheck::PIPELINE_QUEUES`):
`FetchRarCounty` (one county, batched per run), `NormalizeWorkshopRecords`, `ProcessOnrcRelease`,
`ProcessOsmExtract`, `EnrichWorkshopWebsite` (one workshop), `RefreshWorkshopState`. All are
idempotent; the long ones hold a `WithoutOverlapping` lock.

## Search, admin, export, API

`WorkshopSearch` is the single query builder (admin list, API, export) with `WorkshopFilters`;
`QueryInterpreter` reads phrases like "service cutii automate Brașov" or "ITP 4x4 Iași" into
filters and says what it understood. On PostgreSQL names are also matched by trigram similarity
(`pg_trgm`, GIN indexes); radius filters use a bounding box everywhere and exact distance on
PostgreSQL. `WorkshopExporter` streams CSV (UTF-8 with BOM) or JSON; `--public` leaves out sources
not cleared for publication. The internal JSON API lives under `/admin/api/…` behind the admin login
and never exposes raw source content.
