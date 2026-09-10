<?php

namespace App\Console\Commands;

use App\Workshops\Matching\WorkshopDeduplicator;
use App\Workshops\Support\RomanianCounties;
use Illuminate\Console\Command;

class DeduplicateWorkshops extends Command
{
    protected $signature = 'workshops:deduplicate {--county= : Only one county} {--dry-run : Report what would happen, change nothing}';

    protected $description = 'Merge workshops that are certainly the same place and queue the likely ones for review.';

    public function handle(WorkshopDeduplicator $deduplicator): int
    {
        $county = $this->option('county') !== null ? RomanianCounties::resolve($this->option('county')) : null;

        if ($this->option('county') !== null && $county === null) {
            $this->error('Unknown county: '.$this->option('county'));

            return self::FAILURE;
        }

        $counts = $deduplicator->run($county, (bool) $this->option('dry-run'), fn (string $message) => $this->line('  '.$message));

        $this->info(sprintf(
            '%s%d pairs compared, %d merged automatically, %d waiting for review.',
            $this->option('dry-run') ? '[dry run] ' : '',
            $counts['compared'], $counts['auto_merged'], $counts['candidates'],
        ));

        return self::SUCCESS;
    }
}
