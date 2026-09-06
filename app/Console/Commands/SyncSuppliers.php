<?php

namespace App\Console\Commands;

use App\Jobs\SyncSupplierFeed;
use App\Models\Supplier;
use Illuminate\Console\Command;

class SyncSuppliers extends Command
{
    protected $signature = 'suppliers:sync {supplier? : Supplier code} {--mode=catalog : catalog, stock or prices}';

    protected $description = 'Queue supplier catalog, stock, or price synchronization jobs.';

    public function handle(): int
    {
        $mode = (string) $this->option('mode');
        if (! in_array($mode, ['catalog', 'stock', 'prices'], true)) {
            $this->error('Mode must be catalog, stock or prices.');

            return self::INVALID;
        }

        $supplierCode = $this->argument('supplier');
        $suppliers = Supplier::query()
            ->where('is_active', true)
            ->when($supplierCode, fn ($query, $code) => $query->where('code', $code))
            ->when(! $supplierCode, fn ($query) => $query->whereDoesntHave(
                'syncSchedules',
                fn ($scheduleQuery) => $scheduleQuery->where('mode', $mode)->where('is_enabled', true),
            ))
            ->get();

        if ($suppliers->isEmpty()) {
            $this->warn('No active suppliers matched.');

            return self::FAILURE;
        }

        $suppliers->each(fn (Supplier $supplier) => SyncSupplierFeed::dispatch($supplier->id, $mode));
        $this->info("Queued {$mode} sync for {$suppliers->count()} supplier(s).");

        return self::SUCCESS;
    }
}
