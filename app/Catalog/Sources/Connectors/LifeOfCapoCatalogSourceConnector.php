<?php

namespace App\Catalog\Sources\Connectors;

use App\Catalog\Sources\Contracts\CatalogSourceConnector;
use App\Catalog\Sources\Contracts\CatalogSourceReleaseProvider;
use App\Models\CatalogSource;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class LifeOfCapoCatalogSourceConnector implements CatalogSourceConnector, CatalogSourceReleaseProvider
{
    /** @var array<string, array<string, mixed>> */
    private array $resolvedReleases = [];

    public function records(CatalogSource $source, string $mode = 'catalog'): iterable
    {
        $release = $this->release($source, $mode);
        $ref = (string) ($release['release_key'] ?? $source->settings['upstream_ref'] ?? 'main');

        if (in_array($mode, ['catalog', 'vehicles'], true)) {
            yield from $this->vehicleRecords($source, $ref);
        }

        if (in_array($mode, ['catalog', 'generic_parts'], true)) {
            yield from $this->genericPartRecords($source, $ref);
        }

        if (! in_array($mode, ['catalog', 'vehicles', 'generic_parts'], true)) {
            throw new RuntimeException("Unsupported lifeofcapo import mode: {$mode}.");
        }
    }

    public function release(CatalogSource $source, string $mode = 'catalog'): ?array
    {
        $cacheKey = $this->cacheKey($source);
        if (isset($this->resolvedReleases[$cacheKey])) {
            return $this->resolvedReleases[$cacheKey];
        }

        $settings = $source->settings ?? [];
        $ref = (string) ($settings['upstream_ref'] ?? 'main');
        $fixedCommit = trim((string) ($settings['upstream_commit'] ?? ''));

        if ($fixedCommit !== '') {
            return $this->resolvedReleases[$cacheKey] = $this->releasePayload($fixedCommit, $ref);
        }

        $repositoryApi = rtrim((string) ($settings['repository_api_url'] ?? 'https://api.github.com/repos/lifeofcapo/car-api'), '/');
        $response = $this->request($source)
            ->accept('application/vnd.github+json')
            ->get($repositoryApi.'/commits/'.rawurlencode($ref))
            ->throw()
            ->json();
        $sha = trim((string) data_get($response, 'sha', ''));
        throw_if($sha === '', RuntimeException::class, 'Unable to resolve the lifeofcapo upstream commit.');

        return $this->resolvedReleases[$cacheKey] = $this->releasePayload(
            $sha,
            $ref,
            data_get($response, 'commit.committer.date'),
        );
    }

    public function testConnection(CatalogSource $source): array
    {
        $release = $this->release($source);

        return [
            'ok' => filled($release['release_key'] ?? null),
            'status' => 200,
            'upstream_commit' => $release['release_key'] ?? null,
            'upstream_ref' => data_get($release, 'metadata.upstream_ref'),
        ];
    }

    private function vehicleRecords(CatalogSource $source, string $ref): iterable
    {
        $brands = $this->fetchJsonFile($source, $ref, (string) ($source->settings['brands_file'] ?? 'car-brands.json'));

        foreach ($brands as $brand) {
            $brandName = trim((string) data_get($brand, 'brand', ''));
            if ($brandName === '') {
                continue;
            }

            foreach ((array) data_get($brand, 'models', []) as $model) {
                $modelName = trim((string) data_get($model, 'name', ''));
                if ($modelName === '') {
                    continue;
                }

                foreach ((array) data_get($model, 'generations', []) as $generation) {
                    $generationName = trim((string) data_get($generation, 'name', $modelName));
                    $yearFrom = (int) data_get($generation, 'yearFrom', 0);
                    $yearTo = data_get($generation, 'yearTo');
                    if ($yearFrom === 0) {
                        continue;
                    }

                    $identity = implode('|', [$brandName, $modelName, $generationName, $yearFrom, $yearTo ?? 'open']);

                    yield [
                        'record_type' => 'vehicle_generation',
                        'external_id' => 'generation:'.hash('sha256', $identity),
                        'brand' => $brandName,
                        'model' => $modelName,
                        'generation' => $generationName,
                        'yearFrom' => $yearFrom,
                        'yearTo' => $yearTo === null ? null : (int) $yearTo,
                        'upstream_commit' => $ref,
                        'upstream_file' => (string) ($source->settings['brands_file'] ?? 'car-brands.json'),
                    ];
                }
            }
        }
    }

    private function genericPartRecords(CatalogSource $source, string $ref): iterable
    {
        $parts = $this->fetchJsonFile($source, $ref, (string) ($source->settings['parts_file'] ?? 'car-parts.json'));

        foreach ($parts as $part) {
            $slug = trim((string) data_get($part, 'slug', ''));
            $name = trim((string) data_get($part, 'name', ''));
            if ($slug === '' || $name === '') {
                continue;
            }

            yield [
                'record_type' => 'generic_part_taxonomy',
                'external_id' => 'part:'.$slug,
                'slug' => $slug,
                'name' => $name,
                'upstream_commit' => $ref,
                'upstream_file' => (string) ($source->settings['parts_file'] ?? 'car-parts.json'),
            ];
        }
    }

    private function fetchJsonFile(CatalogSource $source, string $ref, string $file): array
    {
        $base = rtrim((string) ($source->settings['raw_base_url'] ?? 'https://raw.githubusercontent.com/lifeofcapo/car-api'), '/');
        $response = $this->request($source)
            ->acceptJson()
            ->get($base.'/'.rawurlencode($ref).'/'.ltrim($file, '/'))
            ->throw();
        $payload = $response->json();
        throw_unless(is_array($payload), RuntimeException::class, "lifeofcapo {$file} did not return a JSON array.");

        return $payload;
    }

    private function releasePayload(string $sha, string $ref, mixed $publishedAt = null): array
    {
        return [
            'release_key' => $sha,
            'published_at' => $publishedAt,
            'retrieved_at' => now(),
            'raw_object_path' => 'https://github.com/lifeofcapo/car-api/tree/'.$sha,
            'metadata' => [
                'repository' => 'lifeofcapo/car-api',
                'upstream_ref' => $ref,
                'commit_sha' => $sha,
            ],
        ];
    }

    private function request(CatalogSource $source): PendingRequest
    {
        $settings = $source->settings ?? [];
        $credentials = $source->credentials ?? [];
        $request = Http::withHeaders([
            'User-Agent' => (string) ($settings['user_agent'] ?? 'eMUD-Automotive-Catalog/1.0'),
        ])->timeout((int) ($settings['timeout_seconds'] ?? 60))
            ->retry((int) ($settings['retry_times'] ?? 3), (int) ($settings['retry_sleep_ms'] ?? 1000));

        if (isset($credentials['bearer_token'])) {
            $request = $request->withToken($credentials['bearer_token']);
        }

        return $request->withHeaders($credentials['headers'] ?? []);
    }

    private function cacheKey(CatalogSource $source): string
    {
        return (string) ($source->getKey() ?? spl_object_id($source));
    }
}
