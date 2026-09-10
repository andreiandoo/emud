<?php

namespace App\Jobs;

use App\Models\WorkshopDataSource;
use App\Models\WorkshopImportRun;
use App\Workshops\Ingestion\DataSourceCatalog;
use App\Workshops\Sources\Osm\OsmExtractDownloader;
use App\Workshops\Sources\Osm\OsmImporter;
use App\Workshops\Sources\Osm\OsmiumExtractor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

/**
 * Downloads the Romania extract when it changed, filters the workshops out with osmium and imports
 * them. The extract is refreshed daily by Geofabrik; importing it monthly is plenty.
 */
class ProcessOsmExtract implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 7200;

    public int $tries = 1;

    public function __construct(public readonly bool $force = false, public readonly ?string $file = null, public readonly ?int $limit = null)
    {
        $this->onQueue('workshops');
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('workshops:osm'))->expireAfter(7300)->dontRelease()];
    }

    public function handle(OsmExtractDownloader $downloader, OsmiumExtractor $osmium, OsmImporter $importer): void
    {
        self::run($downloader, $osmium, $importer, $this->force, $this->file, $this->limit);
    }

    public static function run(OsmExtractDownloader $downloader, OsmiumExtractor $osmium, OsmImporter $importer, bool $force, ?string $file, ?int $limit, ?callable $progress = null): WorkshopImportRun
    {
        $source = WorkshopDataSource::forKey(DataSourceCatalog::OSM);
        $run = WorkshopImportRun::start($source, ['file' => $file, 'force' => $force, 'limit' => $limit]);

        try {
            if ($file === null) {
                if (! $osmium->available()) {
                    throw new RuntimeException('osmium-tool is not installed (apt install osmium-tool), and no --file was given.');
                }

                $extract = $downloader->download($force, $progress);
                $run->updateMetadata(fn (array $metadata): array => $metadata + ['extract' => $extract]);

                // The same extract imported again changes nothing; skip the osmium pass entirely.
                if (! $extract['changed'] && ! $force && $limit === null && $source->stateValue('imported_md5') === $extract['md5']) {
                    $progress && $progress('this extract was already imported; nothing to do (use --force to import it again)');
                    $run->updateMetadata(fn (array $metadata): array => $metadata + ['skipped' => 'extract unchanged']);
                    $run->finish();

                    return $run->fresh();
                }

                $progress && $progress('filtering workshops with osmium');
                $file = $osmium->extract($extract['path']);
            }

            $counts = $importer->import($file, $run, $limit, $progress);
            $run->updateMetadata(fn (array $metadata): array => $metadata + ['counts' => $counts, 'geojson' => $file]);

            if (isset($extract) && $limit === null) {
                $source->putState('imported_md5', $extract['md5']);
            }

            $run->finish();
        } catch (Throwable $exception) {
            $run->fail($exception);

            throw $exception;
        }

        return $run->fresh();
    }
}
