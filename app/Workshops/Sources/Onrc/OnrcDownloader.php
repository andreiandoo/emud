<?php

namespace App\Workshops\Sources\Onrc;

use App\Workshops\Ingestion\SourceHttpClient;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Downloads a release's files to local storage, once. A file already on disk at the size CKAN
 * announces is reused, so an interrupted import does not fetch 1.2 GB again.
 *
 * data.gov.ro ignores Range requests, so a partial download cannot be continued; it is written
 * to a .part file and only renamed when complete. Files an operator fetched some other way can be
 * read from their own directory instead ($from); those are checked against the announced size and
 * never deleted.
 */
class OnrcDownloader
{
    public function __construct(private SourceHttpClient $http) {}

    /**
     * @param  list<string>|null  $names
     * @return array<string, array{path: string, sha256: string, size: int, resource_id: string|null, url: string}>
     */
    public function download(OnrcRelease $release, ?array $names = null, ?callable $progress = null, ?string $from = null): array
    {
        $names ??= [...OnrcDatasetLocator::REQUIRED, ...array_filter(OnrcDatasetLocator::NOMENCLATURE, fn (string $name): bool => $release->resource($name) !== null)];
        $files = [];

        foreach ($names as $name) {
            $resource = $release->resource($name) ?? throw new RuntimeException("{$release->key} has no {$name}.");
            $path = $from !== null ? $this->local($from, $name) : $this->path($release, $name);

            if ($from !== null) {
                if (! $this->isComplete($path, $resource['size'])) {
                    throw new RuntimeException("{$name} is missing from {$from}, or is not the ".number_format((int) $resource['size'])." bytes {$release->key} announces.");
                }

                $progress && $progress("{$name} read from {$from}");
            } elseif (! $this->isComplete($path, $resource['size'])) {
                $progress && $progress("downloading {$name}".($resource['size'] ? ' ('.number_format($resource['size'] / 1_048_576, 0).' MB)' : ''));
                $this->fetch($resource['url'], $path);
            } else {
                $progress && $progress("{$name} already downloaded");
            }

            $files[$name] = [
                'path' => $path,
                'sha256' => hash_file('sha256', $path) ?: '',
                'size' => (int) filesize($path),
                'resource_id' => $resource['id'],
                'url' => $resource['url'],
            ];
        }

        return $files;
    }

    public function directory(OnrcRelease $release): string
    {
        return Storage::disk((string) config('workshops.onrc.disk'))->path(trim((string) config('workshops.onrc.directory'), '/').'/'.$release->key);
    }

    public function forget(OnrcRelease $release): void
    {
        foreach (glob($this->directory($release).'/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->directory($release));
    }

    private function path(OnrcRelease $release, string $name): string
    {
        return $this->directory($release).'/'.strtolower($name);
    }

    /** A file named as data.gov.ro names it (OD_FIRME.CSV) or in lower case. */
    private function local(string $directory, string $name): string
    {
        $directory = rtrim($directory, '/\\');

        return is_file($directory.'/'.$name) ? $directory.'/'.$name : $directory.'/'.strtolower($name);
    }

    private function isComplete(string $path, ?int $expectedSize): bool
    {
        return is_file($path) && ($expectedSize === null ? filesize($path) > 0 : filesize($path) === $expectedSize);
    }

    private function fetch(string $url, string $path): void
    {
        if (! is_dir(dirname($path)) && ! mkdir(dirname($path), 0775, true) && ! is_dir(dirname($path))) {
            throw new RuntimeException('Cannot create '.dirname($path));
        }

        $partial = $path.'.part';

        $this->http->request((int) config('workshops.onrc.request_timeout'), 2, 10_000)
            ->sink($partial)
            ->get($url)
            ->throw();

        if (! rename($partial, $path)) {
            throw new RuntimeException("Cannot move the downloaded file into place: {$path}");
        }
    }
}
