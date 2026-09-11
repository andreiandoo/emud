# Sources

Nothing below claims legal permission that has not been verified. Every source is **internal only
by default** (`workshop_data_sources.is_public_output_allowed = false`); publishing data from one is
a decision taken per source in *Surse ateliere*.

## RAR — Registrul Auto Român

**Where.** The page "Ateliere service auto" (`https://www.rarom.ro/?page_id=889`) links to
`https://portal.rarom.ro/rar-public/registry-search?section=SERVICE`, an Angular application. Its
bundle (`main-es2015.*.js`) calls a public backend at `https://portal.rarom.ro/rarApi`:

| Call | Answer |
| --- | --- |
| `GET /public/address/regions/RO` | the 42 counties as the registry spells them (`Brasov`, `Bucuresti`, `Caras-Severin`) |
| `GET /public/RarPublicAuthorizations/{SERVICE\|ITP\|GPL\|TLV\|B4}?county=…&from=…&to=…` | JSON list of full authorisation documents; `from` inclusive, `to` exclusive |
| `GET /rar-public/assets/i18n/ro.json` | the portal's translation file, which carries the activity nomenclature |

No key, no CAPTCHA, no session. Other query parameters the portal uses: `distance`, `lat`, `lng`,
`searchTerm`, `organisationName`, `taxRegisterNo`, `activity`, `activityClass`, `activityCategory`,
`equipment`, `sectionActivities` (for the A1.7 and B5 sub-sections). The portal pages 2 / 5 / 200 /
400 rows; the importer uses 250 with a 2 s pause between requests.

**Legacy registry.** `prog.rarom.ro/servicenou/?jud=…` and the older per-list pages return 404 or
500 (checked 2026-09-10); `prog.rarom.ro/rarpol/rarpol.asp` is the ITP check by vehicle, not a
workshop list. There is no working legacy registry to fall back on.

**What a record holds.** `status` (only `ACT` is listed), `no` (authorisation number), `exitNo`
(document number, new at every revision), `auditFileNo` (the workshop's audit file), `stationCode`
(ITP), validity dates, revision, `branch.organisationInfo` (name, CUI, registration number, fiscal
indicator, registered office with a GPS pair, sometimes email and phone), `branch.address` (the
point of work, with a GPS pair), `branch.telephoneNo`, the contact person's phone (no name),
`authorizedActivities` (SERVICE), `itpAuthClassDetails` (ITP), `authorizedActivitiesGpl`,
`authorizationTlvActivitiesDetails`, `authorizationB4ActivitiesDetails`, `serviceTypes` (class I/II/III
per activity group), `serviceAuthorizations` (vehicle makes the workshop services under contract),
`authorizationSuspensionInfo`, `noOfWorkstation`, `noOfEmployees`.

**Identity.** There is no record id. The document is identified by `exitNo` together with `no`:
the tachograph section issues two lines at one address under a single exit number (`901` and
`901-1`, four such pairs on 2026-09-10). A workshop's authorisation is followed by `auditFileNo` +
CUI (in ITP two companies can share an audit file, so there the `stationCode` is used). RAR lists
the old and new revision of a renewed authorisation side by side for a while, and occasionally
repeats a row across a page boundary.

**Coordinates are weak.** Of 13 000 SERVICE points of work, 9 881 carry two decimals (town level),
8 986 repeat the registered office's point, 140 have none, and some sit in another county (a
Brașov workshop placed in Bucharest). The parser labels each point `precise`, `approximate`,
`registered_office` (dropped when the workshop is elsewhere) or `implausible` (dropped), and keeps
the raw value.

**Nomenclature.** Codes (`A1_2_1_3`) are words only through the translation file: 122 SERVICE
codes, B4 activities and 91 B4 components, GPL, TLV and ITP classes, ITP limitations,
interdictions and observations. `workshops:rar:discover` stores the live file as a source record;
`RarBundledNomenclature` is a generated copy of the 2026-09-10 file used until then.

**Terms.** The registry is published for the public to find authorised workshops; its reuse
terms are not stated on the pages. Treat it as internal until confirmed with RAR.

## ONRC — trade register open data

**Where.** data.gov.ro (CKAN), organisation `onrc`. A new package "Firme înregistrate la Registrul
Comerțului până la data de …" appears every one to two months (`firme-02-09-2026`, …), with a
matching "Nomenclatoare …" package. `OnrcDatasetLocator` finds the newest one through
`/api/3/action/package_search`; no resource id is pinned.

**Files** (`^`-separated, UTF-8 with BOM, CRLF): `OD_FIRME.CSV` (~700 MB: name, CUI, registration
number, registration date, EUID, legal form, registered address, WEB), `OD_CAEN_AUTORIZAT.CSV`
(~430 MB: registration number, CAEN code, CAEN version — every authorised activity, not only the
main one), `OD_STARE_FIRMA.CSV` (~90 MB: registration number, status code), `N_STARE_FIRMA.CSV`,
`N_CAEN.CSV`. data.gov.ro ignores HTTP Range, so a download cannot be resumed; files are written to
a `.part` file and kept only until the import ends (`ONRC_KEEP_FILES`). Files fetched some other way
are imported with `--from=<directory>`, checked against the sizes CKAN announces and never deleted.

**What is kept.** Vehicle repair is `4520` in CAEN Rev. 2 and `9531` in Rev. 3 (2025): 105 010
companies are authorised for one of them (67 246 + 38 323), 78 258 of which are operating (status
`1048 funcțiune`; 23 248 are struck off, `1084 radiată`). Kept: operating ones, plus every company
RAR lists whatever its status. 14 431 of RAR's 14 500 fiscal codes exist in the register.

**Caveat.** A registered office is where the paperwork lives. ONRC is never taken as the location
of a workshop, and an ONRC-only company gets no workshop until a real place is found.

**Terms.** data.gov.ro publishes ONRC data as open data; check the licence stated on each package
before republishing.

## OpenStreetMap

**Where.** `https://download.geofabrik.de/europe/romania-latest.osm.pbf` with its `.md5`. Nominatim
and Overpass are not queried for the country.

**How.** `osmium tags-filter` keeps `shop=car_repair`, `shop=tyres`, `shop=truck_repair`,
`craft=car_repair`, `amenity=vehicle_inspection` and objects with `service:vehicle:repairs=yes`;
`osmium export -f geojsonseq --geometry-types=point,polygon --add-unique-id=type_id
--attributes=type,id` writes one feature per line with all tags; points and areas only, because by
default a closed way comes out twice, as a line and as an area, under one id. Outlines get the mean
of their vertices as point. Services come from
`shop=*` and `service:vehicle:*=yes` tags.

**Counties.** OSM points rarely state a county. It is taken from `addr:county`, else from the
locality when every known workshop of that name is in one county, else the nearest county seat
(labelled as the approximation it is).

**Terms.** ODbL 1.0. Publishing anything derived requires "© OpenStreetMap contributors" and
share-alike for the derived database.

## Workshop websites and web search

Search is optional: `WORKSHOPS_SEARCH_PROVIDER=brave` with `BRAVE_SEARCH_API_KEY`. Without it,
only websites the sources name are checked. Google Search and Google Maps are never scraped.

The crawler identifies itself (`WORKSHOPS_USER_AGENT`), honours `robots.txt` (RFC 9309: a missing
file allows, a server error forbids), stays on the site's host, follows only contact / about /
services links from the home page, reads at most `WORKSHOPS_CRAWL_MAX_PAGES` pages of at most
1.5 MB each, and waits `WORKSHOPS_CRAWL_DELAY_MS` between requests to one host. Websites are never
crawled on a schedule. Listing sites (listafirme, termene, cylex, paginiaurii, olx…) and social
networks are never accepted as a workshop's website.

Emails kept: generic mailboxes (office@, contact@, service@, programari@…), addresses on the
site's own domain, and addresses the page publishes as a mailto link or in structured data.
A person's address that merely appears in the text is not collected.

## Geocoding

Optional and off by default. `WORKSHOPS_GEOCODER=nominatim` with `WORKSHOPS_NOMINATIM_URL` pointing at
a self-hosted Nominatim. The public nominatim.openstreetmap.org forbids bulk use and is refused
unless `WORKSHOPS_ALLOW_PUBLIC_NOMINATIM=true` for a small supervised batch (one request per second,
`WORKSHOPS_GEOCODER_MAX_PER_RUN`). An answer outside the workshop's county is discarded. Without a
geocoder, addresses stay `pending`; coordinates are never invented.
