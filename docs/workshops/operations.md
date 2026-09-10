# Operations

Commands are given as they are run on the production shell (`~/a-n.ro$`), without a container prefix.

## Before the first import

1. `php artisan migrate` — creates the `workshop_*` tables (and the trigram indexes on PostgreSQL;
   `pg_trgm` must exist, as it already does for the catalogue).
2. The queue worker must drain the **`workshops`** queue. On Ploi, add it to the worker's queue
   list, e.g. `notifications,catalog-search,catalog-canonicalization,catalog-matching,catalog-enrichment,catalog-imports,imports,workshops`.
   A worker without it leaves every queued county waiting forever.
3. For OpenStreetMap only: `sudo apt install osmium-tool` on the server (or set `OSMIUM_BINARY`).

## RAR

```bash
php artisan workshops:rar:discover                      # counties + nomenclature, stored
php artisan workshops:rar:import                        # SERVICE, one queued job per county
php artisan workshops:rar:import --section=all          # SERVICE, ITP, GPL, TLV, B4
php artisan workshops:rar:import --county=BV --sync     # one county, here, with a line per county
php artisan workshops:rar:import --limit=50 --sync      # trial: at most 50 per county, retires nothing
php artisan workshops:rar:import --dry-run              # fetch and count, store nothing
php artisan workshops:rar:import --resume               # continue the last unfinished run
php artisan workshops:rar:import --force                # reparse every record, changed or not
php artisan workshops:rar:stats                         # sections, counties, activities, capabilities
```

A national SERVICE pass is ~55 requests two seconds apart plus parsing: minutes, not hours.

## ONRC

```bash
php artisan workshops:onrc:download         # newest release, ~1.2 GB into storage/app/private/workshops/onrc
php artisan workshops:onrc:import           # queued; downloads if needed, skips a release already imported
php artisan workshops:onrc:import --sync --keep-files
php artisan workshops:onrc:import --force   # the same release again
php artisan workshops:onrc:match            # re-match unmatched / ambiguous companies (no download)
```

## OpenStreetMap

```bash
php artisan workshops:osm:download          # only when Geofabrik's md5 changed
php artisan workshops:osm:import            # queued: download, osmium, import; skips an unchanged extract
php artisan workshops:osm:import --file=/path/workshops.geojsonseq --sync
php artisan workshops:osm:match             # re-match waiting points, e.g. after a RAR import
```

## Enrichment and upkeep

```bash
php artisan workshops:geocode --limit=200               # only with a geocoder configured
php artisan workshops:web:discover --limit=100          # find and validate websites
php artisan workshops:web:crawl --limit=50              # read confirmed websites
php artisan workshops:web:crawl --refresh               # also sites read > 90 days ago
php artisan workshops:classify                          # rebuild services/capabilities from stored data
php artisan workshops:deduplicate --dry-run             # what would be merged / queued
php artisan workshops:deduplicate
php artisan workshops:normalize --status=failed --sync  # re-read failed records, no fetching
```

## Refresh and schedule

```bash
php artisan workshops:refresh                    # RAR (all sections), ONRC, OSM — queued
php artisan workshops:refresh --source=rar --county=BV --sync
php artisan workshops:refresh --source=rar --dry-run
```

`workshops:refresh` never crawls websites. The scheduler entries in `routes/console.php` stay off
until `WORKSHOPS_SCHEDULE_ENABLED=true`: RAR weekly (`WORKSHOPS_RAR_CRON`, Sunday 04:30), OSM and
ONRC monthly (both skip themselves when nothing changed), deduplication weekly.

## Status and export

```bash
php artisan workshops:status
php artisan workshops:export --format=csv
php artisan workshops:export --format=json --county=BV --service=4x4_drivetrain --rar --has-email
php artisan workshops:export --capability=offroad --capability=4x4 --output=/tmp/offroad.csv
php artisan workshops:export --public            # only sources cleared for publication
```

Exports default to `storage/app/private/workshops/exports/`. The admin list exports exactly the
filtered view as CSV.

## Storage

- ONRC: ~1.2 GB per release while importing (deleted afterwards unless kept).
- OSM: ~330 MB extract plus a small filtered file, kept to compare checksums.
- Database: the raw RAR payloads (~17 000 records) are the largest part, some tens of MB.

## Tests

`tests/Unit/Workshops` and `tests/Feature/Workshops` run offline against fixtures in
`tests/Fixtures/Workshops` (anonymised RAR documents, a small ONRC set, an OSM sequence, website
pages); every HTTP call is faked. `php artisan test --filter=Workshop` runs them; CI runs them on
PostgreSQL with the rest of the suite. The live sources are exercised only by the commands above.

## Troubleshooting

- *A county keeps failing*: the run ends `completed_with_errors` and lists it in
  `metadata.failed_partitions`; run `workshops:rar:import --resume` later.
- *"returned X of Y held; nothing retired"*: a county answered with far fewer records than before.
  Nothing was withdrawn; if the drop is real, it will be retired on a pass that looks complete.
- *Records failed to parse*: `workshops:status` counts them; the reason is on each record
  (admin → Surse ateliere → record); fix the parser, then `workshops:normalize --status=failed`.
- *osmium-tool is not installed*: install it, or import a GeoJSON sequence produced elsewhere with `--file`.
