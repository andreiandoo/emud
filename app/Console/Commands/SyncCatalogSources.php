<?php

namespace App\Console\Commands;

use App\Jobs\SyncCatalogSource;
use App\Models\CatalogSource;
use Illuminate\Console\Command;

class SyncCatalogSources extends Command
{
    protected $signature = 'catalog:sources:sync {source? : Source code} {--mode=catalog} {--all : Queue all active sources}';

    protected $description = 'Queue automotive catalog source synchronization jobs.';

    public function handle(): int
    {
        $code = $this->argument('source');
        $query = CatalogSource::query()->where('is_active', true);

        if ($code) {
            $query->where('code', $code);
        } elseif (! $this->option('all')) {
            $this->error('Provide a source code or use --all.');
            return self::FAILURE;
        }

        $sources = $query->get();
        if ($sources->isEmpty()) {
            $this->warn('No matching active catalog sources.');
            return self::FAILURE;
        }

        foreach ($sources as $source) {
            SyncCatalogSource::dispatch($source->id, (string) $this->option('mode'));
            $this->line("Queued {$source->code} ({$this->option('mode')}).");
        }

        return self::SUCCESS;
    }
}
