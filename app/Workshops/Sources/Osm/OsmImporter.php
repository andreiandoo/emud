<?php

namespace App\Workshops\Sources\Osm;

use App\Models\Workshop;
use App\Models\WorkshopImportRun;
use App\Models\WorkshopSourceLink;
use App\Workshops\Data\SourceRecordData;
use App\Workshops\Data\StoreResult;
use App\Workshops\Domain\WorkshopStateRefresher;
use App\Workshops\Ingestion\RecordNormalizers;
use App\Workshops\Ingestion\SourceRecordStore;
use RuntimeException;
use Throwable;

/**
 * Stores every workshop feature osmium exported as its own source record ("node/123",
 * "way/456"), with the complete tag set, then matches it. A feature the next extract no longer
 * has is retired, not deleted.
 */
class OsmImporter
{
    public function __construct(private SourceRecordStore $store, private RecordNormalizers $normalizers, private WorkshopStateRefresher $state) {}

    /** @return array{seen: int, created: int, updated: int, unchanged: int, skipped: int, failed: int, retired: int} */
    public function import(string $geojsonSeq, WorkshopImportRun $run, ?int $limit = null, ?callable $progress = null): array
    {
        $handle = @fopen($geojsonSeq, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Cannot open {$geojsonSeq}");
        }

        $startedAt = now()->startOfSecond();
        $source = $run->dataSource;
        $counts = ['seen' => 0, 'created' => 0, 'updated' => 0, 'unchanged' => 0, 'skipped' => 0, 'failed' => 0, 'retired' => 0];

        try {
            while (($line = fgets($handle)) !== false) {
                // GeoJSON Text Sequences start every record with an ASCII record separator.
                $line = trim($line, "\x1e \t\r\n");

                if ($line === '') {
                    continue;
                }

                $payload = $this->payload(json_decode($line, true));

                if ($payload === null) {
                    $counts['skipped']++;
                    $run->tally('skipped');

                    continue;
                }

                if ($limit !== null && $counts['seen'] >= $limit) {
                    break;
                }

                $counts['seen']++;
                $run->tally('discovered');

                try {
                    $result = $this->store->store($source, new SourceRecordData(
                        recordType: 'poi',
                        externalId: "{$payload['type']}/{$payload['id']}",
                        payload: $payload,
                        sourceReference: "https://www.openstreetmap.org/{$payload['type']}/{$payload['id']}",
                    ), $run);

                    $counts[$result->outcome === StoreResult::DUPLICATE ? 'unchanged' : $result->outcome]++;
                    $run->tally('fetched');
                    $run->tally($result->outcome === StoreResult::DUPLICATE ? 'skipped' : $result->outcome);

                    if ($result->needsParsing() && ! $this->normalizers->normalizeSafely($result->record)) {
                        $counts['failed']++;
                        $run->tally('failed');
                    }
                } catch (Throwable $exception) {
                    report($exception);
                    $counts['failed']++;
                    $run->tally('failed');
                }

                if ($progress && $counts['seen'] % 1000 === 0) {
                    $progress(number_format($counts['seen']).' features read');
                }
            }
        } finally {
            fclose($handle);
        }

        if ($limit === null) {
            $retired = $this->store->retireUnseen($source, 'poi', $startedAt);
            $counts['retired'] = $retired->count();
            $run->tally('retired', $counts['retired']);

            // A workshop only OpenStreetMap knew goes inactive with its point; one RAR also
            // lists loses nothing but the OSM evidence.
            $workshopIds = WorkshopSourceLink::query()->whereIn('source_record_id', $retired->all())->pluck('workshop_id')->unique();
            Workshop::query()->whereIn('id', $workshopIds->all())->each(fn (Workshop $workshop) => $this->state->refresh($workshop));
        }

        return $counts;
    }

    /**
     * The part of a feature worth keeping: its OSM identity, one point, and every tag.
     *
     * @return array{type: string, id: int, lat: float, lng: float, area: bool, tags: array<string, string>}|null
     */
    public function payload(mixed $feature): ?array
    {
        if (! is_array($feature)) {
            return null;
        }

        $properties = (array) ($feature['properties'] ?? []);
        [$type, $id] = $this->identity($feature, $properties);
        $centre = $this->centre($feature['geometry'] ?? null);

        if ($type === null || $centre === null) {
            return null;
        }

        $tags = [];

        foreach ($properties as $key => $value) {
            if (! str_starts_with((string) $key, '@') && is_scalar($value)) {
                $tags[(string) $key] = (string) $value;
            }
        }

        ksort($tags);

        return [
            'type' => $type,
            'id' => $id,
            'lat' => round($centre[1], 7),
            'lng' => round($centre[0], 7),
            'area' => ($feature['geometry']['type'] ?? 'Point') !== 'Point',
            'tags' => $tags,
        ];
    }

    /** @return array{0: string|null, 1: int|null} */
    private function identity(array $feature, array $properties): array
    {
        if (isset($properties['@type'], $properties['@id']) && in_array($properties['@type'], ['node', 'way', 'relation'], true)) {
            return [$properties['@type'], (int) $properties['@id']];
        }

        // osmium's type_id ids: n123, w123, r123, and a<n> for areas (2×way id, or 2×relation id + 1).
        if (preg_match('/^([nwra])(\d+)$/', (string) ($feature['id'] ?? ''), $match) !== 1) {
            return [null, null];
        }

        $number = (int) $match[2];

        return match ($match[1]) {
            'n' => ['node', $number],
            'w' => ['way', $number],
            'r' => ['relation', $number],
            default => $number % 2 === 0 ? ['way', intdiv($number, 2)] : ['relation', intdiv($number - 1, 2)],
        };
    }

    /**
     * A point for the feature: the node itself, or the mean of an outline's vertices, which is
     * inside the building for the compact shapes workshops have.
     *
     * @return array{0: float, 1: float}|null [lng, lat]
     */
    private function centre(mixed $geometry): ?array
    {
        if (! is_array($geometry) || ! is_array($geometry['coordinates'] ?? null)) {
            return null;
        }

        $points = match ($geometry['type'] ?? null) {
            'Point' => [$geometry['coordinates']],
            'LineString', 'MultiPoint' => $geometry['coordinates'],
            'Polygon' => array_slice($geometry['coordinates'][0] ?? [], 0, -1),
            'MultiPolygon' => array_merge(...array_map(fn (array $polygon): array => array_slice($polygon[0] ?? [], 0, -1), $geometry['coordinates'])) ?: [],
            default => [],
        };

        $points = array_values(array_filter($points, fn (mixed $point): bool => is_array($point) && is_numeric($point[0] ?? null) && is_numeric($point[1] ?? null)));

        if ($points === []) {
            return null;
        }

        return [
            array_sum(array_column($points, 0)) / count($points),
            array_sum(array_column($points, 1)) / count($points),
        ];
    }
}
