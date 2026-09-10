<?php

namespace App\Console\Commands;

use App\Jobs\ProcessOsmExtract;
use App\Workshops\Sources\Osm\OsmExtractDownloader;
use App\Workshops\Sources\Osm\OsmImporter;
use App\Workshops\Sources\Osm\OsmiumExtractor;
use Illuminate\Console\Command;
use Throwable;

class ImportOsmWorkshops extends Command
{
    protected $signature = 'workshops:osm:import
        {--file= : Import an existing osmium GeoJSON sequence instead of downloading and filtering}
        {--force : Download the extract even if unchanged}
        {--limit= : At most this many features (trial runs; nothing is retired)}
        {--sync : Run here instead of on the queue}';

    protected $description = 'Import car repair, tyre, truck repair and inspection points from OpenStreetMap and match them.';

    public function handle(OsmExtractDownloader $downloader, OsmiumExtractor $osmium, OsmImporter $importer): int
    {
        $limit = $this->option('limit') !== null ? max(1, (int) $this->option('limit')) : null;

        if (! $this->option('sync')) {
            ProcessOsmExtract::dispatch((bool) $this->option('force'), $this->option('file'), $limit);
            $this->info('Queued the OpenStreetMap import on the "workshops" queue.');

            return self::SUCCESS;
        }

        try {
            $run = ProcessOsmExtract::run($downloader, $osmium, $importer, (bool) $this->option('force'), $this->option('file'), $limit, fn (string $message) => $this->line('  '.$message));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $counts = $run->metadata['counts'] ?? [];
        $this->info(sprintf(
            'OpenStreetMap: %d features, %d new, %d changed, %d unchanged, %d skipped, %d failed, %d retired.',
            $counts['seen'] ?? 0, $counts['created'] ?? 0, $counts['updated'] ?? 0, $counts['unchanged'] ?? 0, $counts['skipped'] ?? 0, $counts['failed'] ?? 0, $counts['retired'] ?? 0,
        ));

        return self::SUCCESS;
    }
}
