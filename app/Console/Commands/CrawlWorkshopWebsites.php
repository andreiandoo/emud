<?php

namespace App\Console\Commands;

use App\Jobs\EnrichWorkshopWebsite;
use App\Models\Workshop;
use App\Models\WorkshopWebsiteCandidate;
use App\Workshops\Web\WebsiteEnricher;
use Illuminate\Console\Command;

class CrawlWorkshopWebsites extends Command
{
    protected $signature = 'workshops:web:crawl
        {--limit=50 : At most this many websites}
        {--workshop= : Only this workshop id}
        {--refresh : Also re-read websites crawled longer ago than WORKSHOPS_RECRAWL_AFTER_DAYS}
        {--sync : Run here instead of queueing one job per workshop}';

    protected $description = 'Read confirmed workshop websites (a few pages each, robots.txt respected) for contacts and services.';

    public function handle(WebsiteEnricher $enricher): int
    {
        $stale = now()->subDays((int) config('workshops.web.recrawl_after_days'));

        $sites = WorkshopWebsiteCandidate::query()
            ->where('validation_status', WorkshopWebsiteCandidate::ACCEPTED)
            ->when($this->option('workshop'), fn ($query, $id) => $query->where('workshop_id', (int) $id))
            ->where(fn ($query) => $query->whereNull('crawled_at')->when($this->option('refresh'), fn ($query) => $query->orWhere('crawled_at', '<', $stale)))
            ->whereHas('workshop', fn ($query) => $query->whereNull('merged_into_id'))
            ->orderByRaw('case when crawled_at is null then 0 else 1 end')
            ->orderByDesc('confidence')
            ->limit(max(1, (int) $this->option('limit')))
            ->get();

        if ($sites->isEmpty()) {
            $this->info('No confirmed website is waiting to be read.');

            return self::SUCCESS;
        }

        if (! $this->option('sync')) {
            $sites->each(fn (WorkshopWebsiteCandidate $site) => EnrichWorkshopWebsite::dispatch($site->workshop_id, 'crawl'));
            $this->info("Queued {$sites->count()} websites for reading.");

            return self::SUCCESS;
        }

        foreach ($sites as $site) {
            $workshop = Workshop::query()->find($site->workshop_id);

            if ($workshop === null) {
                continue;
            }

            $counts = $enricher->enrich($workshop, $site);
            $this->line(sprintf('  %s: %d pages, %d emails, %d phones, %d services', $site->domain, $counts['pages'], $counts['emails'], $counts['phones'], $counts['services']));
        }

        return self::SUCCESS;
    }
}
