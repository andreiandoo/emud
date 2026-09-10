# National workshop registry

A maintained database of every car repair workshop in Romania that can be found from official and
open sources, built inside the Laravel application and refreshed from those sources on a schedule.

It serves four uses: a searchable workshop directory, a B2B lead list, finding workshops able to
fit and service 4x4 / off-road parts sold by the shop, and a geographic service-partner network.

## What it holds

| Source | What it proves | Records (first read, 2026-09-10) |
| --- | --- | --- |
| **RAR** public registry (`portal.rarom.ro`) | A workshop is authorised by the Romanian Automotive Register, at this point of work, for these exact activity codes | 13 000 service, 3 083 ITP, 217 GPL/GNC, 365 tachograph, 654 modification (B4) authorisations |
| **ONRC** open data (data.gov.ro) | Legal identity, status and every authorised CAEN activity of a company | 105 010 companies authorised for vehicle repair (4520 / 9531), 78 258 of them operating |
| **OpenStreetMap** (Geofabrik extract) | Someone mapped a workshop here; its point, and often phone and website | extracted with osmium |
| **Workshop websites** | What a workshop says it does, and its email and social contacts | crawled on demand, a few pages per site |

A **company** (legal entity) and a **workshop** (a place where vehicles are worked on) are
separate: one company can run twenty workshops, and a registered office is not a workshop.

Every value keeps its source. Raw records are stored exactly as received, every workshop is
linked to the records it was built from, every contact and service says which source and page it
came from, and services keep the kind of evidence behind them: *authorised by RAR*, *declared on
the website*, *tagged on OpenStreetMap*, *entered manually* — never merged into one.

## Quick start

```bash
php artisan migrate
php artisan workshops:rar:discover          # county list + activity nomenclature from RAR
php artisan workshops:rar:import --section=all   # queued: one job per county per section
php artisan workshops:status                # coverage, latest runs
```

The queue worker must include the `workshops` queue (see [operations.md](operations.md)).

Then, when wanted: `workshops:onrc:import`, `workshops:osm:import` (needs `osmium-tool`),
`workshops:deduplicate`, `workshops:web:discover` / `workshops:web:crawl`,
`workshops:export --format=csv`.

## Where to look

- [architecture.md](architecture.md) — tables, pipeline, matching, confidence, capabilities.
- [sources.md](sources.md) — each source: how it is read, what it gives, its caveats and terms.
- [operations.md](operations.md) — every command, the scheduler, the worker, storage, tests.
- [PROGRESS.md](PROGRESS.md) — what is done, what is not, and what was discovered on the way.

In the back office: **Service auto → Registru național** (search, filters, export),
**Ateliere de verificat** (merge / match review) and **Surse ateliere** (sources, runs, coverage).
