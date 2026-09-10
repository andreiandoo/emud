<?php

namespace App\Jobs;

use App\Models\Workshop;
use App\Models\WorkshopWebsiteCandidate;
use App\Workshops\Web\WebsiteDiscoverer;
use App\Workshops\Web\WebsiteEnricher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

/**
 * One workshop's website: find it (discover) or read it (crawl). One workshop per job, so a slow
 * site holds up nothing else and a failure is retried on its own.
 */
class EnrichWorkshopWebsite implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public int $tries = 2;

    public int $backoff = 900;

    public function __construct(public readonly int $workshopId, public readonly string $step = 'discover')
    {
        $this->onQueue('workshops');
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping("workshops:web:{$this->workshopId}"))->expireAfter(700)->dontRelease()];
    }

    public function handle(WebsiteDiscoverer $discoverer, WebsiteEnricher $enricher): void
    {
        $workshop = Workshop::query()->find($this->workshopId);

        if ($workshop === null || $workshop->merged_into_id !== null) {
            return;
        }

        if ($this->step === 'discover') {
            $discoverer->discover($workshop);

            return;
        }

        $site = $workshop->websiteCandidates()->where('validation_status', WorkshopWebsiteCandidate::ACCEPTED)->orderByDesc('confidence')->first();

        if ($site !== null) {
            $enricher->enrich($workshop, $site);
        }
    }
}
