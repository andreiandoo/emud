<?php

namespace App\Console\Commands;

use App\Workshops\Sources\Onrc\OnrcDatasetLocator;
use App\Workshops\Sources\Onrc\OnrcDownloader;
use Illuminate\Console\Command;
use Throwable;

class DownloadOnrcDataset extends Command
{
    protected $signature = 'workshops:onrc:download {--release= : A specific data.gov.ro package name, e.g. firme-02-09-2026}';

    protected $description = 'Locate the newest ONRC release on data.gov.ro and download its files (about 1.2 GB).';

    public function handle(OnrcDatasetLocator $locator, OnrcDownloader $downloader): int
    {
        try {
            $release = $locator->latest($this->option('release'));
            $this->info("{$release->title} ({$release->key})");
            $files = $downloader->download($release, progress: fn (string $message) => $this->line('  '.$message));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(['File', 'Size (MB)', 'SHA-256', 'Resource'], collect($files)->map(fn (array $file, string $name): array => [
            $name, number_format($file['size'] / 1_048_576, 1), substr($file['sha256'], 0, 16).'…', $file['resource_id'],
        ])->values()->all());

        $this->line('Stored in '.$downloader->directory($release));

        return self::SUCCESS;
    }
}
