<?php

namespace App\Console\Commands;

use App\Directory\WorkshopListingSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Builds and refreshes the public directory's listings from the national workshop registry.
 *
 * Run after the registry's imports. Safe to repeat: a listing is matched to its workshop, so a
 * second run updates rather than duplicates, and never touches a field an admin has taken over.
 */
class SyncWorkshopListings extends Command
{
    protected $signature = 'service-shops:sync-registry
        {--county= : Only workshops in this county, by RAR county code (e.g. CJ)}
        {--limit= : Stop after this many workshops}
        {--dry-run : Count what would change and write nothing}';

    protected $description = 'Create and refresh public workshop listings from the national registry';

    public function handle(WorkshopListingSync $sync): int
    {
        $county = $this->option('county') ? (string) $this->option('county') : null;
        $limit = $this->option('limit') !== null ? max(1, (int) $this->option('limit')) : null;
        $dryRun = (bool) $this->option('dry-run');

        $eligible = $sync->eligible()
            ->when($county !== null, fn ($query) => $query->where('county_code', strtoupper((string) $county)))
            ->count();

        $this->info("Eligible workshops: {$eligible}".($dryRun ? ' (dry run, nothing will be written)' : ''));

        if ($eligible === 0) {
            $this->warn('Nothing can be listed. Mark the RAR sources as publishable in Admin → Surse ateliere first.');
        }

        // A dry run does the real work inside a transaction and rolls it back, so the counts are
        // exactly what a real run would produce.
        if ($dryRun) {
            DB::beginTransaction();
        }

        try {
            $totals = $sync->run($county, $limit, function (int $done): void {
                $this->output->write("\r  processed: {$done}");
            });
        } finally {
            if ($dryRun) {
                DB::rollBack();
            }
        }

        $this->newLine();
        $this->table(
            ['Created', 'Updated', 'Moved to the kept workshop', 'Taken down'],
            [[$totals['created'], $totals['updated'], $totals['relinked'], $totals['unpublished']]],
        );

        return self::SUCCESS;
    }
}
