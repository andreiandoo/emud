<?php

namespace App\Console\Commands;

use App\Jobs\EnrichWorkshopWebsite;
use App\Models\Workshop;
use App\Workshops\Contracts\WebSearchProvider;
use App\Workshops\Support\RomanianCounties;
use App\Workshops\Web\WebsiteDiscoverer;
use Illuminate\Console\Command;

class FindWorkshopWebsites extends Command
{
    protected $signature = 'workshops:web:discover
        {--limit=100 : At most this many workshops}
        {--county= : Only one county}
        {--workshop= : Only this workshop id}
        {--sync : Run here instead of queueing one job per workshop}';

    protected $description = 'Find and validate each workshop\'s own website (from OSM, ONRC, email domains, and a search API if configured).';

    public function handle(WebsiteDiscoverer $discoverer, WebSearchProvider $search): int
    {
        if (! $search->isConfigured()) {
            $this->line('No search API configured (WORKSHOPS_SEARCH_PROVIDER): only websites the sources already name are checked.');
        }

        $county = $this->option('county') !== null ? RomanianCounties::resolve($this->option('county')) : null;
        $workshops = Workshop::query()
            ->canonical()
            ->where('is_active', true)
            ->when($this->option('workshop'), fn ($query, $id) => $query->whereKey((int) $id), fn ($query) => $query->where('website_status', 'pending'))
            ->when($county, fn ($query, string $code) => $query->where('county_code', $code))
            // Workshops the sources already point to a website for go first: they need no search.
            ->orderByRaw('case when exists (select 1 from workshop_website_candidates c where c.workshop_id = workshops.id) then 0 else 1 end')
            ->orderByDesc('offroad_score')
            ->orderBy('id')
            ->limit(max(1, (int) $this->option('limit')))
            ->pluck('id');

        if ($workshops->isEmpty()) {
            $this->info('No workshop is waiting for website discovery.');

            return self::SUCCESS;
        }

        if (! $this->option('sync')) {
            $workshops->each(fn (int $id) => EnrichWorkshopWebsite::dispatch($id, 'discover'));
            $this->info("Queued website discovery for {$workshops->count()} workshops.");

            return self::SUCCESS;
        }

        $found = 0;

        foreach (Workshop::query()->whereIn('id', $workshops->all())->get() as $workshop) {
            $result = $discoverer->discover($workshop);
            $found += $result['accepted'] !== null ? 1 : 0;
            $this->line(sprintf('  #%d %s — %s', $workshop->id, $workshop->name, $result['accepted'] ?? 'no website confirmed'));
        }

        $this->info("{$found} of {$workshops->count()} workshops have a confirmed website.");

        return self::SUCCESS;
    }
}
