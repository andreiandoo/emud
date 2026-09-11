# Progress

Last updated 2026-09-10.

## DONE

| Milestone | State |
| --- | --- |
| 0 — inspect the application | Laravel 12, PHP 8.4, PostgreSQL (SQLite in tests), Redis queues, Livewire 4 admin, catalogue-source pipeline used as the pattern. Integration approach: a feature folder `app/Workshops`, flat models, jobs on a dedicated `workshops` queue, admin pages under *Service auto*. |
| 1 — RAR source discovery | The new registry is an Angular SPA over a public JSON API (`portal.rarom.ro/rarApi`); the legacy lists are gone. Endpoints, parameters, paging, identifiers and nomenclature documented in [sources.md](sources.md). |
| 2 — raw record layer | `workshop_data_sources`, `workshop_source_records` (SHA-256, first/last seen, parse status, current flag, identity key), `workshop_import_runs`. |
| 3 — national RAR importer | All five sections, county by county, paced (2 s), retried with backoff, resumable, retirement with a truncation guard, inline or one queued job per county, `--county`, `--limit`, `--resume`, `--force`, `--dry-run`. Opt-in live check: `workshops:rar:probe`. |
| 4 — RAR normalisation | Companies, workshops (point of work), contacts per source, authorisations, every activity code with hierarchy, categories, limitations, restrictions (ITP interdictions, suspensions in force), observations, B4 components as their own rows. |
| 5 — RAR → services | 57-key taxonomy; every code mapped with a confidence; services kept per evidence type. Capabilities: 4x4, permanent AWD, EV, hybrid, trucks, ITP-for-4x4, off-road score. |
| 6 — ONRC | CKAN discovery of the newest release, streamed four-pass import, CAEN 4520 (Rev. 2) and 9531 (Rev. 3), statuses, deterministic matching, leads without workshops; files fetched by hand imported with `--from`. |
| 7 — OpenStreetMap | Geofabrik download with md5, osmium filter/export, POI parser, locality → county resolution, scored matching with review states. |
| 8 — coordinates | Quality per point (precise / approximate / registered office / implausible), OSM points preferred, `Geocoder` with a Null default and a self-hosted Nominatim driver that refuses the public server for bulk use. |
| 9 — website discovery | `WebSearchProvider` (Null default, Brave), candidates from OSM, ONRC and email domains, validation by phone / CUI / name / town / street, listing and social sites refused. |
| 10 — crawler and contacts | robots.txt (RFC 9309), same host, page and size caps, per-host delay; mailto, tel, WhatsApp, Facebook, Instagram, JSON-LD; business emails only. |
| 11 — service classification | Rule-based Romanian classifier behind a `ServiceClassifier` contract, evidence snippets kept, declared services never marked as authorised. |
| 12 — deduplication and confidence | Blocked candidate pairs, pair scoring, auto-merge only when certain, never across two places of one company or two RAR authorisations at different addresses, and never across two companies; review queue, provenance-preserving merge; workshop confidence score. |
| 13 — other RAR registries | ITP, GPL/GNC, tachographs, B4 modifications as separate sources, linked to the same place when company and address agree. |
| 14 — admin, search, export, API | *Registru național* (filters, plain-phrase search, CSV export), workshop page with full provenance, raw record viewer, *Surse ateliere*, *Ateliere de verificat*; `WorkshopSearch` with pg_trgm; `workshops:export`; `/admin/api/workshops`. |
| 15 — scheduling, docs, tests | Scheduler entries behind `WORKSHOPS_SCHEDULE_ENABLED`; README, architecture, sources, operations; 124 tests, 724 assertions (unit + feature, offline fixtures). CI green on PostgreSQL. |

## IN PROGRESS

- Nothing in code. The first production import is the next operational step (see NEXT).

## NEXT

1. Deploy, `php artisan migrate`, add `workshops` to the Ploi queue worker's queue list.
2. `php artisan workshops:rar:discover` then `php artisan workshops:rar:import --section=all`.
3. `sudo apt install osmium-tool`, then `php artisan workshops:osm:import`.
4. `php artisan workshops:onrc:import` (needs ~1.2 GB of free disk while it runs; if the download
   keeps breaking, fetch the files by hand and add `--from=<directory>`).
5. `php artisan workshops:deduplicate`, then review *Ateliere de verificat*.
6. Website pass in batches, starting with the off-road-relevant workshops:
   `php artisan workshops:web:discover --limit=200` and `workshops:web:crawl --limit=100`.
7. Decide per source what may be published (*Surse ateliere → Publicabilă*) before any of it
   reaches the storefront directory, and whether to connect this registry to `service_shops`.
8. Switch on the schedule once the first runs look right: `WORKSHOPS_SCHEDULE_ENABLED=true`.

## BLOCKED (on a decision or a credential, not on code)

- **Web search API**: without `BRAVE_SEARCH_API_KEY` (or another provider) websites are found
  only from OSM tags, ONRC's WEB field and business email domains.
- **Geocoder**: without a self-hosted Nominatim (`WORKSHOPS_NOMINATIM_URL`) addresses without a
  good point stay `pending`. OSM matches supply street-level points for many of them.
- **Publication rights**: RAR's reuse terms are not stated; OSM is ODbL (attribution and
  share-alike); ONRC is open data per data.gov.ro's licence. All sources are internal-only until
  someone decides otherwise.
- **osmium-tool** must be installed on the server for the OSM import.

## DISCOVERIES

- **RAR has no working legacy registry.** `prog.rarom.ro/servicenou` and the other old lists answer
  404 / 500; `rarpol.asp` is the ITP check by vehicle. The only list is the new portal.
- **The portal is an Angular app over a public, unauthenticated JSON API**, no CAPTCHA:
  `/rarApi/public/RarPublicAuthorizations/{SERVICE|ITP|GPL|TLV|B4}?county=&from=&to=` and
  `/rarApi/public/address/regions/RO`. `from` is inclusive and `to` exclusive. County names are
  the registry's ASCII spellings; Bucharest is `Bucuresti` (code `B` here).
- **No record id.** `exitNo` identifies a document and changes with every revision; `auditFileNo`
  follows the workshop but is shared across companies in ITP, where `stationCode` is stable. RAR
  lists old and new revisions side by side for a while and occasionally repeats a row.
- **Only active authorisations are listed** (`status = ACT`), so a record disappearing means the
  authorisation lapsed or was withdrawn.
- **RAR coordinates are weak**: 76 % of SERVICE points carry two decimals (town level), 69 % repeat
  the registered office's point, 1 % are missing, and some are in the wrong county.
- **The activity nomenclature is only in the portal's translation file** (`assets/i18n/ro.json`),
  including 91 B4 components that name off-road equipment outright (C9 bull bar, C40 and C82
  winches, C48 roll cage, C56 suspension tuning, C25 hardtop, C30 roof rack, C65 spare-wheel carrier).
- **A1.1.2 (hybrid engines) had been granted to no workshop at all**, while A1.1.3 (electric motors)
  is held by 426 workshops. Hybrid capability therefore stays unknown unless a website says so.
- **"Tracțiune pe mai multe axe"** appears in 6 468 SERVICE authorisations and permanent all-wheel
  drive (A1.2.1.3 / A1.2.2.3) in 5 236: common, which is why the off-road score looks beyond it.
- **ITP stations can be barred from permanent 4x4** (`ITP_INTERDICTION_AUTO_PERMANENT_ALLWHEEL`),
  which answers "ITP 4x4 in X" directly.
- **ONRC publishes every authorised CAEN activity**, not only the main one; vehicle repair is 4520
  (Rev. 2) and 9531 (Rev. 3). 105 010 companies hold one; 78 258 are operating; 23 248 are struck off.
  14 431 of the 14 500 fiscal codes RAR lists exist in the register.
- **data.gov.ro ignores HTTP Range**: a partial ONRC download cannot be resumed.
- **OSM**: the Romania extract holds about 1 800 workshop objects (1 323 nodes, 486 ways) with the
  tags used here — far fewer than RAR's 13 000, but often with the only street-level point and website.
- **Two companies, one address.** On the first national pass every automatic merge (64 of 64) joined
  two *different* companies at one address sharing a phone: mostly an owner's service firm and a
  separate ITP firm ("OEN SERVICE SRL" / "OEN ITP SRL"), but also tenants of one yard (a
  cooperative's compound in Bucharest). A workshop has one company, so these now wait for review.
- **One address, many spellings**: "DN65" / "DN 65", "18A" / "18 A", with or without the postal code,
  the floor area ("spațiu în suprafață de 150 mp"), "clădirea C1" / "construcție C1". Worse, a
  Bucharest sector number had been accepted as the house number, so "nr. 5, sector 3" could pass
  for "nr. 7, sector 3". 102 pairs of one company's own records were waiting for review only because
  of such spellings; the address comparison now reads them as one.
- **Sites repeat their footer**: one site gave 24 email sightings for 3 distinct generic mailboxes.
- **PostgreSQL checks what SQLite does not**: the first production import failed 282 ITP stations,
  whose class list ("ITP_CLASS_1,ITP_CLASS_2,ITP_CLASS_3", 35 characters) did not fit a 32-character
  column. Comparing every varchar with the longest value stored locally found one more (ONRC company
  states of up to 294 characters). Both columns were widened; the failed records are read again
  from the raw layer with `workshops:normalize --status=failed`.
- **Round numbers can be real**: SERVICE returned exactly 13 000 rows. Not a cap: every county ended
  on a short page (Bucharest, the largest, 1 222 = four pages of 250 and one of 222), and one row
  was repeated across a page boundary (Constanța), leaving 12 999 records.

## Statistics

A representative end-to-end run on 2026-09-10 against the live registry, on a developer machine:
SQLite instead of PostgreSQL, and pyosmium instead of osmium-tool for the OSM filter (same tags,
same GeoJSON-sequence output). Figures from `workshops:rar:stats` and `workshops:status`.

### RAR, imported twice

| Section | Rows returned | Records stored | Second run |
| --- | ---: | ---: | --- |
| SERVICE | 13 000 | 12 999 | 12 999 unchanged |
| ITP | 3 083 | 3 083 | 3 083 unchanged |
| GPL/GNC | 217 | 217 | 217 unchanged |
| TLV (tachographs) | 365 | 365 | 365 unchanged |
| B4 (modifications) | 654 | 654 | 654 unchanged |
| **Total** | **17 319** | **17 318** | **0 new, 0 changed, 0 retired** |

- 42 counties × 5 sections, 237 requests at a 2 s pace. First run 31 min 54 s; second run 17 min
  14 s (the requests only: nothing to read again). Before the foreign-key indexes the first run
  took 57 min.
- Parse failures: 0. Distinct activity codes in force: 216. Hybrid engines (A1.1.2): granted to nobody.
- OpenStreetMap: 1 809 features (1 323 nodes, 486 ways) imported in 4 min 40 s; the second import
  found all 1 809 unchanged.

### The registry, rebuilt from the stored records with the final code

The derived tables were emptied and all 19 127 stored records (17 318 RAR, 1 809 OSM) read again
from the raw layer, fetching nothing: 14 min 53 s, 0 failures. Then ONRC from the 2 September 2026
release (files already on disk, `--from`), deduplication twice and a small website pass.

| Measure | Count |
| --- | ---: |
| Workshops (places) | 16 530 |
| … holding RAR service / ITP / GPL-GNC / tachograph / B4 authorisations | 12 986 / 3 063 / 217 / 358 / 654 |
| … known only from OpenStreetMap | 1 029 |
| Companies | 79 511 (14 509 known from RAR, 65 002 ONRC leads without a workshop) |
| RAR workshops whose company ONRC confirms (CUI) | 12 918 |
| With a phone | 15 538 (94 %) |
| With an email | 3 470 |
| With a website | 179 |
| With coordinates | 14 889, of which street-level 4 594 (the rest are RAR's town-level points) |
| Without coordinates (no geocoder configured) | 1 641 |
| 4x4: multi-axle transmission authorised | 6 460 |
| Off-road specialists (score ≥ 60 with specialist evidence) | 17 |
| ITP stations that may inspect permanent 4x4 / barred from it | 3 010 / 46 |
| B4 winch installation (C40, C82) / bull bar (C9) | 52 / 5 workshops |
| EV capable (A1.1.3) | 425 |
| Truck / bus capable | 4 074 |
| Parse failures / records waiting to be read | 0 / 0 |

Also from `workshops:rar:stats`: 648 companies with several workshops; permanent all-wheel drive
authorised in 5 228 workshops; 6 038 off-road relevant (score ≥ 40); 15 948 with a point-of-work
address (the other 582 are OSM points without one); hybrid capable: 1, declared on a workshop's own
website ("de la autoturisme hibride Toyota și Lexus…"), since RAR grants A1.1.2 to nobody.

| County | Workshops | RAR service | ITP | With phone |
| --- | ---: | ---: | ---: | ---: |
| Alba (AB) | 288 | 223 | 64 | 273 |
| Arad (AR) | 417 | 350 | 63 | 401 |
| Argeș (AG) | 509 | 406 | 91 | 488 |
| Bacău (BC) | 455 | 385 | 77 | 444 |
| Bihor (BH) | 651 | 560 | 91 | 642 |
| Bistrița-Năsăud (BN) | 278 | 235 | 52 | 269 |
| Botoșani (BT) | 278 | 231 | 42 | 270 |
| Brașov (BV) | 618 | 501 | 94 | 574 |
| Brăila (BR) | 177 | 133 | 29 | 163 |
| București (B) | 1 681 | 1 220 | 315 | 1 470 |
| Buzău (BZ) | 296 | 209 | 52 | 255 |
| Caraș-Severin (CS) | 234 | 191 | 49 | 231 |
| Călărași (CL) | 164 | 129 | 26 | 158 |
| Cluj (CJ) | 743 | 586 | 141 | 701 |
| Constanța (CT) | 521 | 425 | 106 | 492 |
| Covasna (CV) | 161 | 129 | 37 | 149 |
| Dâmbovița (DB) | 365 | 302 | 60 | 356 |
| Dolj (DJ) | 407 | 272 | 106 | 372 |
| Galați (GL) | 324 | 243 | 65 | 307 |
| Giurgiu (GR) | 175 | 123 | 48 | 167 |
| Gorj (GJ) | 249 | 196 | 49 | 241 |
| Harghita (HR) | 234 | 187 | 53 | 221 |
| Hunedoara (HD) | 303 | 239 | 66 | 284 |
| Ialomița (IL) | 137 | 108 | 33 | 132 |
| Iași (IS) | 596 | 461 | 91 | 535 |
| Ilfov (IF) | 820 | 666 | 155 | 793 |
| Maramureș (MM) | 465 | 407 | 67 | 462 |
| Mehedinți (MH) | 193 | 147 | 41 | 184 |
| Mureș (MS) | 471 | 378 | 77 | 452 |
| Neamț (NT) | 439 | 339 | 78 | 394 |
| Olt (OT) | 211 | 145 | 49 | 199 |
| Prahova (PH) | 598 | 439 | 112 | 542 |
| Satu Mare (SM) | 267 | 233 | 37 | 257 |
| Sălaj (SJ) | 337 | 290 | 35 | 329 |
| Sibiu (SB) | 320 | 244 | 57 | 301 |
| Suceava (SV) | 535 | 446 | 116 | 524 |
| Teleorman (TR) | 176 | 131 | 38 | 161 |
| Timiș (TM) | 552 | 388 | 110 | 498 |
| Tulcea (TL) | 113 | 96 | 20 | 111 |
| Vaslui (VS) | 216 | 163 | 59 | 212 |
| Vâlcea (VL) | 304 | 218 | 62 | 278 |
| Vrancea (VN) | 252 | 212 | 50 | 246 |

**ONRC.** 105 010 companies in the register hold CAEN 4520 (Rev. 2) or 9531 (Rev. 3); 79 793 were
kept (the operating ones, plus every company RAR lists whatever its status) in 57 min 35 s.
14 791 ONRC records matched a RAR company by CUI; 65 002 became leads.

**OpenStreetMap.** Of 1 809 features: 186 matched to a RAR workshop (114 by phone, 59 by name and
address, 11 by website, 2 by name and proximity), 201 probable and 17 ambiguous waiting for review,
1 029 new workshops, the rest unnamed or unplaceable. 127 RAR workshops gained an OSM link, and
with it usually the only street-level point.

**Deduplication.** 7 888 pairs compared in 8 s. With the final rules: 3 merged automatically (one
company, one address spelt two ways), 535 waiting for review (36 of one company, 495 of two
different companies at one address, 4 with an OSM-only side), 29 earlier candidates withdrawn as no
longer likely; a second run changed nothing. The review queue holds 753 items (535 pairs, 218 OSM
matches). Before the fixes the same data produced 64 automatic merges, every one across two companies.

**Websites** (bounded: 12 Brașov workshops, no search API). 6 websites confirmed, all from OSM
tags; 25 pages read on 6 sites (robots.txt respected); 8 distinct email addresses (generic mailboxes
or the site's own domain), 10 phones, 44 declared services.

**Exports** (`storage/app/private/workshops/exports/`, not committed): the national registry as
CSV and JSON, the off-road shortlist, and the 4x4 workshops that have a phone.

### Workshops inspected by hand

- **An ordinary service**: ARINOVIS IMPEX SRL, Săcele (BV). SERVICE 1042, seven activities (A1,
  A1.3, A1.3.1, A3, A3.1, B1, B1.3), one mobile, a town-level point (confidence 25), trucks yes, 4x4
  unknown: no transmission activity, and absence is not taken as a "no".
- **4x4-relevant**: EURO CAR TRADING SRL, Odorheiu Secuiesc (HR), off-road score 90. SERVICE with
  permanent AWD (A1.2.1.3, A1.2.2.3), ITP classes 1–3 with permanent 4x4 allowed, B4 with C9 bull
  bar, C25 hardtop, C40 winch and C48 roll cage: three authorisations of one company at one address,
  one place.
- **A multi-location company**: PILKINGTON AUTOMOTIVE ROMANIA SA, 28 places across the country,
  each its own workshop under one company.
- **Many activities**: GEO-STING SRL, Petrești (DB). Two SERVICE documents of one audit file (the
  old and new revision, 94 activities each) and a B4 authorisation (C11, C35, C36, C38, C40).
- **An OSM match**: BODNĂRESCU COMPANY SRL, Nădlac (AR), matched by phone (98). The OSM node brought
  a street-level point and a second email.
- **OSM only**: "LABELL", Brașov: a named car-repair node without an address; county from the
  nearest seat, general repair only.
- **Website enrichment**: EURO-EST TURBO CENTER SRL, Brașov. Website from its OSM tag, accepted
  because the page carries the RAR phone; three pages gave an address on the site's own domain,
  two landlines, a second mobile, WhatsApp, Facebook and declared steering, diagnostics and truck work.
- **Waiting for a person**: RĂDĂCINI AUTO MOTOR SRL and RĂDĂCINI MOTORS SRL, one address, two shared
  phones, two CUIs; ATOMIC AUTO SERVICE SRL inside the AUTOMECANICA cooperative's compound.

### Limits of this run

- SQLite, not PostgreSQL: trigram search and the jsonb behaviour are exercised only by CI.
- pyosmium stood in for osmium-tool; the production osmium path is covered by tests, not by this run.
- No search API and no geocoder: 16 518 workshops still wait for website enrichment, 1 641 have no
  point, and 10 295 have only RAR's town-level one.
- Website discovery and crawling were deliberately tiny (12 workshops, 6 sites).
- Nobody has worked through the review queue; its 495 two-company pairs need a person.
- Nothing is cleared for publication: RAR's reuse terms are not stated, OSM is ODbL (attribution,
  share-alike), ONRC is open data under data.gov.ro's licence.
