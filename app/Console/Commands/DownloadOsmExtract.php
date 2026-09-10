<?php

namespace App\Console\Commands;

use App\Workshops\Sources\Osm\OsmExtractDownloader;
use Illuminate\Console\Command;
use Throwable;

class DownloadOsmExtract extends Command
{
    protected $signature = 'workshops:osm:download {--force : Download even if Geofabrik\'s checksum has not changed}';

    protected $description = 'Download the Romania OpenStreetMap extract from Geofabrik, only when it changed.';

    public function handle(OsmExtractDownloader $downloader): int
    {
        try {
            $extract = $downloader->download((bool) $this->option('force'), fn (string $message) => $this->line('  '.$message));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf('%s: %s MB, md5 %s%s', $extract['path'], number_format($extract['size'] / 1_048_576, 1), $extract['md5'], $extract['changed'] ? '' : ' (unchanged)'));

        return self::SUCCESS;
    }
}
