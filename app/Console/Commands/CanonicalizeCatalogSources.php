<?php

namespace App\Console\Commands;

use App\Jobs\CanonicalizeCatalogSourceRecords;
use App\Models\CatalogSource;
use Illuminate\Console\Command;

class CanonicalizeCatalogSources extends Command
{
    protected $signature = 'catalog:sources:canonicalize {source? : Source code} {--all} {--limit=50000}';

    protected $description = 'Queue canonicalization of staged catalog source records.';

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
            $this->warn('No active matching catalog sources.');

            return self::FAILURE;
        }

        foreach ($sources as $source) {
            CanonicalizeCatalogSourceRecords::dispatch($source->id, max(1, (int) $this->option('limit')));
            $this->line("Queued canonicalization for {$source->code}.");
        }

        return self::SUCCESS;
    }
}
