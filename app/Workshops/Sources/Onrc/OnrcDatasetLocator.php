<?php

namespace App\Workshops\Sources\Onrc;

use App\Workshops\Ingestion\SourceHttpClient;
use RuntimeException;

/**
 * Finds the newest ONRC release through data.gov.ro's CKAN API instead of a pinned resource id:
 * every release is a new package with new ids, and the file names are what stays the same.
 */
class OnrcDatasetLocator
{
    /** The files the import reads. The first three must be present for a release to count. */
    public const REQUIRED = ['OD_FIRME.CSV', 'OD_CAEN_AUTORIZAT.CSV', 'OD_STARE_FIRMA.CSV'];

    public const NOMENCLATURE = ['N_STARE_FIRMA.CSV', 'N_CAEN.CSV', 'N_VERSIUNE_CAEN.CSV'];

    public function __construct(private SourceHttpClient $http) {}

    public function latest(?string $key = null): OnrcRelease
    {
        $packages = $this->packages();
        $prefix = (string) config('workshops.onrc.dataset_prefix');

        $dataset = collect($packages)->first(function (array $package) use ($prefix, $key): bool {
            $resources = $this->resources($package);

            return ($key === null ? str_starts_with((string) $package['name'], $prefix) : $package['name'] === $key)
                && array_diff(self::REQUIRED, array_keys($resources)) === [];
        });

        if ($dataset === null) {
            throw new RuntimeException($key === null ? 'No ONRC release with the company, CAEN and status files was found on data.gov.ro.' : "ONRC release [{$key}] was not found on data.gov.ro.");
        }

        // The nomenclature package is published alongside each release with the same date in its
        // name ("firme-02-09-2026" and "nomenclatoare-02-09-2026"); failing that, the newest one.
        $nomenclatures = collect($packages)->filter(fn (array $package): bool => str_starts_with((string) $package['name'], (string) config('workshops.onrc.nomenclature_prefix')));
        $date = substr((string) $dataset['name'], strlen((string) config('workshops.onrc.dataset_prefix')));
        $nomenclature = $nomenclatures->first(fn (array $package): bool => str_ends_with((string) $package['name'], $date)) ?? $nomenclatures->first();

        $resources = $this->resources($dataset) + ($nomenclature === null ? [] : $this->resources($nomenclature));

        return new OnrcRelease((string) $dataset['name'], (string) ($dataset['title'] ?? $dataset['name']), $dataset['metadata_created'] ?? null, $resources);
    }

    /** @return list<array<string, mixed>> */
    private function packages(): array
    {
        $json = $this->http->request(60, 3, 2000)
            ->acceptJson()
            ->get(rtrim((string) config('workshops.onrc.ckan_url'), '/').'/package_search', [
                'fq' => 'organization:'.config('workshops.onrc.organization'),
                'sort' => 'metadata_created desc',
                'rows' => 24,
            ])
            ->throw()
            ->json();

        if (! is_array($json['result']['results'] ?? null)) {
            throw new RuntimeException('data.gov.ro returned an unexpected package list.');
        }

        return array_values(array_filter($json['result']['results'], 'is_array'));
    }

    /** @return array<string, array{id: string|null, url: string, size: int|null, last_modified: string|null}> */
    private function resources(array $package): array
    {
        $resources = [];

        foreach ((array) ($package['resources'] ?? []) as $resource) {
            $name = strtoupper(trim((string) ($resource['name'] ?? basename((string) ($resource['url'] ?? '')))));

            if ($name === '' || empty($resource['url'])) {
                continue;
            }

            $resources[$name] = [
                'id' => $resource['id'] ?? null,
                'url' => (string) $resource['url'],
                'size' => isset($resource['size']) && is_numeric($resource['size']) ? (int) $resource['size'] : null,
                'last_modified' => $resource['last_modified'] ?? $resource['created'] ?? null,
            ];
        }

        return $resources;
    }
}
