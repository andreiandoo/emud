<?php

namespace App\Console\Commands;

use App\Jobs\RefreshWorkshopState;
use App\Models\Workshop;
use Illuminate\Console\Command;

class ClassifyWorkshops extends Command
{
    protected $signature = 'workshops:classify
        {--workshop= : Only this workshop id}
        {--county= : Only one county code}
        {--sync : Run here instead of queueing chunks}';

    protected $description = 'Re-derive services and capabilities (4x4, EV, trucks, off-road) from stored RAR, OSM and website data.';

    public function handle(): int
    {
        $ids = Workshop::query()
            ->canonical()
            ->when($this->option('workshop'), fn ($query, $id) => $query->whereKey((int) $id))
            ->when($this->option('county'), fn ($query, $county) => $query->where('county_code', strtoupper((string) $county)))
            ->orderBy('id')
            ->pluck('id');

        if ($ids->isEmpty()) {
            $this->info('No workshops to classify.');

            return self::SUCCESS;
        }

        foreach ($ids->chunk(200) as $chunk) {
            $job = new RefreshWorkshopState($chunk->values()->all());
            $this->option('sync') ? dispatch_sync($job) : dispatch($job);
        }

        $this->info(($this->option('sync') ? 'Classified ' : 'Queued classification of ').$ids->count().' workshops.');

        return self::SUCCESS;
    }
}
