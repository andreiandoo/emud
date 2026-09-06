<?php

namespace App\Console\Commands;

use App\Jobs\MatchSupplierProductsToCatalog;
use App\Models\Supplier;
use Illuminate\Console\Command;

class MatchSupplierCatalog extends Command
{
    protected $signature = 'suppliers:match-catalog {supplier? : Supplier code} {--all} {--limit=10000}';
    protected $description = 'Match staged supplier products to canonical technical CatalogPart records.';

    public function handle(): int
    {
        $code = $this->argument('supplier');
        if (! $code && ! $this->option('all')) {
            $this->error('Provide a supplier code or use --all.');
            return self::FAILURE;
        }

        if ($code) {
            $supplier = Supplier::query()->where('code', $code)->first();
            if (! $supplier) { $this->error('Supplier not found.'); return self::FAILURE; }
            MatchSupplierProductsToCatalog::dispatch($supplier->id, (int) $this->option('limit'));
        } else {
            MatchSupplierProductsToCatalog::dispatch(null, (int) $this->option('limit'));
        }

        $this->info('Catalog matching queued.');
        return self::SUCCESS;
    }
}
