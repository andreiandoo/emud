<?php

namespace App\Jobs;

use App\Models\Workshop;
use App\Models\WorkshopWebsiteCandidate;
use App\Workshops\Domain\WorkshopStateRefresher;
use App\Workshops\Web\WebsiteEnricher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Re-derives services and capabilities for a chunk of workshops from what is already stored:
 * after the classifier or the RAR mapping improves, nothing needs fetching again.
 */
class RefreshWorkshopState implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public int $tries = 1;

    /** @param list<int> $workshopIds */
    public function __construct(public readonly array $workshopIds, public readonly bool $reclassifyWebsites = true)
    {
        $this->onQueue('workshops');
    }

    public function handle(WorkshopStateRefresher $state, WebsiteEnricher $enricher): void
    {
        Workshop::query()->canonical()->whereIn('id', $this->workshopIds)->orderBy('id')->each(function (Workshop $workshop) use ($state, $enricher): void {
            if ($this->reclassifyWebsites && $workshop->websiteCandidates()->where('validation_status', WorkshopWebsiteCandidate::ACCEPTED)->exists()) {
                $enricher->classify($workshop);
            }

            $state->refresh($workshop);
        });
    }
}
