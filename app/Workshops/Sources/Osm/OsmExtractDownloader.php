<?php

namespace App\Workshops\Sources\Osm;

use App\Models\WorkshopDataSource;
use App\Workshops\Ingestion\DataSourceCatalog;
use App\Workshops\Ingestion\SourceHttpClient;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * The Romania extract from Geofabrik, downloaded only when it changed.
 *
 * Geofabrik publishes an .md5 next to every extract. When it matches the one recorded for the file
 * already on disk, nothing is downloaded; otherwise the new file is fetched to a .part file,
 * checked against the published checksum, and only then moved into place.
 */
class OsmExtractDownloader
{
    public function __construct(private SourceHttpClient $http) {}

    /** @return array{path: string, md5: string, size: int, changed: bool, last_modified: string|null} */
    public function download(bool $force = false, ?callable $progress = null): array
    {
        $source = WorkshopDataSource::forKey(DataSourceCatalog::OSM);
        $url = (string) config('workshops.osm.extract_url');
        $expected = $this->publishedMd5($url);
        $path = $this->path();
        $state = (array) $source->stateValue('extract', []);

        if (! $force && is_file($path) && $expected !== null && ($state['md5'] ?? null) === $expected) {
            $progress && $progress('extract unchanged since '.($state['downloaded_at'] ?? 'the last download'));

            return ['path' => $path, 'md5' => $expected, 'size' => (int) filesize($path), 'changed' => false, 'last_modified' => $state['last_modified'] ?? null];
        }

        if (! is_dir(dirname($path)) && ! mkdir(dirname($path), 0775, true) && ! is_dir(dirname($path))) {
            throw new RuntimeException('Cannot create '.dirname($path));
        }

        $progress && $progress("downloading {$url}");
        $partial = $path.'.part';
        $response = $this->http->request((int) config('workshops.osm.request_timeout'), 2, 10_000)->sink($partial)->get($url)->throw();
        $actual = (string) md5_file($partial);

        if ($expected !== null && $actual !== $expected) {
            @unlink($partial);

            throw new RuntimeException("The downloaded extract does not match Geofabrik's checksum ({$actual} instead of {$expected}).");
        }

        if (! rename($partial, $path)) {
            throw new RuntimeException("Cannot move the extract into place: {$path}");
        }

        $info = ['path' => $path, 'md5' => $actual, 'size' => (int) filesize($path), 'changed' => true, 'last_modified' => $response->header('Last-Modified') ?: null];
        $source->putState('extract', [
            'md5' => $actual,
            'size' => $info['size'],
            'last_modified' => $info['last_modified'],
            'downloaded_at' => now()->toIso8601String(),
            'url' => $url,
        ]);

        return $info;
    }

    public function path(): string
    {
        return Storage::disk((string) config('workshops.osm.disk'))->path(trim((string) config('workshops.osm.directory'), '/').'/romania-latest.osm.pbf');
    }

    private function publishedMd5(string $url): ?string
    {
        try {
            $body = trim($this->http->request(30, 2, 2000)->get($url.'.md5')->throw()->body());
        } catch (Throwable) {
            return null;
        }

        return preg_match('/^([a-f0-9]{32})\b/i', $body, $match) === 1 ? strtolower($match[1]) : null;
    }
}
