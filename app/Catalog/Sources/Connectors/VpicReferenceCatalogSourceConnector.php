<?php

namespace App\Catalog\Sources\Connectors;

use App\Catalog\Sources\Contracts\CatalogSourceConnector;
use App\Catalog\Sources\Contracts\CatalogSourceReleaseProvider;
use App\Models\CatalogSource;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class VpicReferenceCatalogSourceConnector implements CatalogSourceConnector, CatalogSourceReleaseProvider
{
    /** @return iterable<array<string, mixed>> */
    public function records(CatalogSource $source, string $mode = 'catalog'): iterable
    {
        yield from match ($mode) {
            'catalog' => $this->catalog($source),
            'manufacturers' => $this->manufacturers($source),
            'makes' => $this->makes($source),
            'models' => $this->models($source),
            'manufacturer_links', 'wmis' => $this->manufacturerLinks($source),
            'vehicle_types' => $this->vehicleTypes($source),
            'model_years' => $this->modelYears($source),
            default => throw new RuntimeException("Unsupported vPIC reference import mode: {$mode}."),
        };
    }

    /** @return array<string, mixed>|null */
    public function release(CatalogSource $source, string $mode = 'catalog'): ?array
    {
        $date = now()->toDateString();

        return [
            'release_key' => "vpic-api:{$date}",
            'retrieved_at' => now(),
            'raw_object_path' => rtrim($this->apiBaseUrl($source), '/').'/api/vehicles',
            'metadata' => [
                'provider' => 'National Highway Traffic Safety Administration',
                'transport' => 'vPIC JSON API',
                'mode' => $mode,
                'api_is_rate_controlled' => true,
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function testConnection(CatalogSource $source): array
    {
        $payload = $this->get($source, '/api/vehicles/GetAllMakes');

        return [
            'ok' => is_array($payload['Results'] ?? null),
            'status' => 200,
            'count' => (int) ($payload['Count'] ?? count($payload['Results'] ?? [])),
        ];
    }

    /** @return iterable<array<string, mixed>> */
    private function catalog(CatalogSource $source): iterable
    {
        foreach ($this->manufacturers($source) as $row) {
            yield $row;
        }
        foreach ($this->makes($source) as $row) {
            yield $row;
        }
        foreach ($this->models($source) as $row) {
            yield $row;
        }
    }

    /** @return iterable<array<string, mixed>> */
    private function manufacturers(CatalogSource $source): iterable
    {
        $page = 1;

        while (true) {
            $payload = $this->get($source, '/api/vehicles/GetAllManufacturers', ['page' => $page]);
            $rows = $this->results($payload);
            if ($rows === []) {
                break;
            }

            foreach ($rows as $row) {
                $id = $this->int($row, ['Mfr_ID', 'MfrId', 'ManufacturerId']);
                $name = $this->string($row, ['Mfr_Name', 'MfrName', 'ManufacturerName']);
                if (! $id || ! $name) {
                    continue;
                }

                yield array_merge($row, [
                    'record_type' => 'vpic_manufacturer',
                    'external_id' => "vpic:manufacturer:{$id}",
                    'vpic_manufacturer_id' => $id,
                    'manufacturer_name' => $name,
                ]);
            }

            if (count($rows) < 100) {
                break;
            }

            $page++;
            $this->pace($source);
        }
    }

    /** @return iterable<array<string, mixed>> */
    private function makes(CatalogSource $source): iterable
    {
        $payload = $this->get($source, '/api/vehicles/GetAllMakes');
        foreach ($this->results($payload) as $row) {
            $id = $this->int($row, ['Make_ID', 'MakeId']);
            $name = $this->string($row, ['Make_Name', 'MakeName']);
            if (! $id || ! $name) {
                continue;
            }

            yield array_merge($row, [
                'record_type' => 'vpic_make',
                'external_id' => "vpic:make:{$id}",
                'vpic_make_id' => $id,
                'make_name' => $name,
            ]);
        }
    }

    /** @return iterable<array<string, mixed>> */
    private function models(CatalogSource $source): iterable
    {
        $payload = $this->get($source, '/api/vehicles/GetModelsForMakeId/0');
        foreach ($this->results($payload) as $row) {
            $makeId = $this->int($row, ['Make_ID', 'MakeId']);
            $makeName = $this->string($row, ['Make_Name', 'MakeName']);
            $modelId = $this->int($row, ['Model_ID', 'ModelId']);
            $modelName = $this->string($row, ['Model_Name', 'ModelName']);
            if (! $makeId || ! $makeName || ! $modelId || ! $modelName) {
                continue;
            }

            yield array_merge($row, [
                'record_type' => 'vpic_model',
                'external_id' => "vpic:model:{$modelId}",
                'vpic_make_id' => $makeId,
                'make_name' => $makeName,
                'vpic_model_id' => $modelId,
                'model_name' => $modelName,
            ]);
        }
    }

    /** @return iterable<array<string, mixed>> */
    private function manufacturerLinks(CatalogSource $source): iterable
    {
        $manufacturers = iterator_to_array($this->manufacturers($source), false);
        $checkpoint = $this->checkpoint($source, 'manufacturer_links');
        $offset = max(0, (int) ($checkpoint['offset'] ?? 0));
        $batchSize = max(1, (int) ($source->settings['manufacturer_link_batch_size'] ?? 25));
        $processed = 0;

        for ($index = $offset; $index < count($manufacturers) && $processed < $batchSize; $index++, $processed++) {
            $manufacturer = $manufacturers[$index];
            $manufacturerId = (int) ($manufacturer['vpic_manufacturer_id'] ?? 0);
            if (! $manufacturerId) {
                continue;
            }

            $makes = $this->results($this->get($source, "/api/vehicles/GetMakeForManufacturer/{$manufacturerId}"));
            foreach ($makes as $row) {
                $makeId = $this->int($row, ['Make_ID', 'MakeId']);
                $makeName = $this->string($row, ['Make_Name', 'MakeName']);
                if (! $makeId || ! $makeName) {
                    continue;
                }

                yield array_merge($row, [
                    'record_type' => 'vpic_make_manufacturer',
                    'external_id' => "vpic:make_manufacturer:{$manufacturerId}:{$makeId}",
                    'vpic_manufacturer_id' => $manufacturerId,
                    'vpic_make_id' => $makeId,
                    'make_name' => $makeName,
                ]);
            }

            $this->pace($source);
            $wmis = $this->results($this->get($source, "/api/vehicles/GetWMIsForManufacturer/{$manufacturerId}"));
            foreach ($wmis as $row) {
                $wmi = $this->string($row, ['WMI', 'Wmi']);
                if (! $wmi) {
                    continue;
                }
                $vehicleType = $this->string($row, ['VehicleType', 'VehicleTypeName', 'Vehicle_Type']);
                $vehicleTypeId = $this->int($row, ['VehicleTypeId', 'VehicleType_ID']);
                $makeId = $this->int($row, ['MakeId', 'Make_ID']);

                yield array_merge($row, [
                    'record_type' => 'vpic_wmi',
                    'external_id' => 'vpic:wmi:'.$manufacturerId.':'.strtoupper($wmi).':'.($vehicleTypeId ?: 'any'),
                    'vpic_manufacturer_id' => $manufacturerId,
                    'vpic_make_id' => $makeId,
                    'wmi' => strtoupper($wmi),
                    'vehicle_type' => $vehicleType,
                    'vehicle_type_id' => $vehicleTypeId,
                ]);
            }

            $this->saveCheckpoint($source, 'manufacturer_links', ['offset' => $index + 1]);
            $this->pace($source);
        }

        if ($offset + $processed >= count($manufacturers)) {
            $this->clearCheckpoint($source, 'manufacturer_links');
        }
    }

    /** @return iterable<array<string, mixed>> */
    private function vehicleTypes(CatalogSource $source): iterable
    {
        $makes = iterator_to_array($this->makes($source), false);
        $checkpoint = $this->checkpoint($source, 'vehicle_types');
        $offset = max(0, (int) ($checkpoint['offset'] ?? 0));
        $batchSize = max(1, (int) ($source->settings['vehicle_type_batch_size'] ?? 50));
        $processed = 0;

        for ($index = $offset; $index < count($makes) && $processed < $batchSize; $index++, $processed++) {
            $make = $makes[$index];
            $makeId = (int) ($make['vpic_make_id'] ?? 0);
            if (! $makeId) {
                continue;
            }

            $rows = $this->results($this->get($source, "/api/vehicles/GetVehicleTypesForMakeId/{$makeId}"));
            foreach ($rows as $row) {
                $typeName = $this->string($row, ['VehicleTypeName', 'VehicleType']);
                $typeId = $this->int($row, ['VehicleTypeId', 'VehicleType_ID']);
                if (! $typeName) {
                    continue;
                }

                yield array_merge($row, [
                    'record_type' => 'vpic_make_vehicle_type',
                    'external_id' => 'vpic:make_type:'.$makeId.':'.($typeId ?: hash('sha1', $typeName)),
                    'vpic_make_id' => $makeId,
                    'make_name' => $make['make_name'] ?? null,
                    'vehicle_type' => $typeName,
                    'vehicle_type_id' => $typeId,
                ]);
            }

            $this->saveCheckpoint($source, 'vehicle_types', ['offset' => $index + 1]);
            $this->pace($source);
        }

        if ($offset + $processed >= count($makes)) {
            $this->clearCheckpoint($source, 'vehicle_types');
        }
    }

    /** @return iterable<array<string, mixed>> */
    private function modelYears(CatalogSource $source): iterable
    {
        $makes = iterator_to_array($this->makes($source), false);
        $yearFrom = max(1996, (int) ($source->settings['model_year_from'] ?? 1996));
        $yearTo = max($yearFrom, (int) ($source->settings['model_year_to'] ?? (now()->year + 1)));
        $checkpoint = $this->checkpoint($source, 'model_years');
        $makeOffset = max(0, (int) ($checkpoint['make_offset'] ?? 0));
        $resumeYear = max($yearFrom, (int) ($checkpoint['year'] ?? $yearFrom));
        $batchSize = max(1, (int) ($source->settings['model_year_make_batch_size'] ?? 5));
        $processedMakes = 0;

        for ($index = $makeOffset; $index < count($makes) && $processedMakes < $batchSize; $index++, $processedMakes++) {
            $make = $makes[$index];
            $makeId = (int) ($make['vpic_make_id'] ?? 0);
            $startYear = $index === $makeOffset ? $resumeYear : $yearFrom;
            if (! $makeId) {
                continue;
            }

            for ($year = $startYear; $year <= $yearTo; $year++) {
                $rows = $this->results($this->get($source, "/api/vehicles/GetModelsForMakeIdYear/makeId/{$makeId}/modelyear/{$year}"));
                foreach ($rows as $row) {
                    $modelId = $this->int($row, ['Model_ID', 'ModelId']);
                    $modelName = $this->string($row, ['Model_Name', 'ModelName']);
                    $vehicleType = $this->string($row, ['VehicleTypeName', 'VehicleType']);
                    if (! $modelId || ! $modelName) {
                        continue;
                    }

                    yield array_merge($row, [
                        'record_type' => 'vpic_model_year',
                        'external_id' => 'vpic:model_year:'.$modelId.':'.$year.':'.($vehicleType ? hash('sha1', $vehicleType) : 'any'),
                        'vpic_make_id' => $makeId,
                        'make_name' => $make['make_name'] ?? null,
                        'vpic_model_id' => $modelId,
                        'model_name' => $modelName,
                        'model_year' => $year,
                        'vehicle_type' => $vehicleType,
                    ]);
                }

                $this->saveCheckpoint($source, 'model_years', ['make_offset' => $index, 'year' => $year + 1]);
                $this->pace($source);
            }

            $this->saveCheckpoint($source, 'model_years', ['make_offset' => $index + 1, 'year' => $yearFrom]);
        }

        if ($makeOffset + $processedMakes >= count($makes)) {
            $this->clearCheckpoint($source, 'model_years');
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function results(array $payload): array
    {
        $rows = $payload['Results'] ?? [];

        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    /** @param array<string, mixed> $query */
    private function get(CatalogSource $source, string $path, array $query = []): array
    {
        $url = rtrim($this->apiBaseUrl($source), '/').'/'.ltrim($path, '/');
        $query['format'] = 'json';
        $response = $this->request($source)->get($url, $query)->throw();
        $payload = $response->json();

        if (! is_array($payload)) {
            throw new RuntimeException("vPIC returned a non-JSON response for {$path}.");
        }

        return $payload;
    }

    private function request(CatalogSource $source): PendingRequest
    {
        $settings = $source->settings ?? [];

        return Http::withHeaders([
            'User-Agent' => (string) ($settings['user_agent'] ?? 'eMUD-Automotive-Catalog/1.0'),
            'Accept' => 'application/json',
        ])->timeout((int) ($settings['reference_timeout_seconds'] ?? 180))
            ->retry(
                max(1, (int) ($settings['reference_retry_times'] ?? 4)),
                max(100, (int) ($settings['reference_retry_sleep_ms'] ?? 1500)),
                throw: false,
            );
    }

    private function pace(CatalogSource $source): void
    {
        $milliseconds = max(0, (int) ($source->settings['reference_request_interval_ms'] ?? 250));
        if ($milliseconds > 0) {
            usleep($milliseconds * 1000);
        }
    }

    /** @return array<string, mixed> */
    private function checkpoint(CatalogSource $source, string $mode): array
    {
        $checkpoints = $source->settings['reference_checkpoints'] ?? [];
        $checkpoint = is_array($checkpoints) ? ($checkpoints[$mode] ?? []) : [];

        return is_array($checkpoint) ? $checkpoint : [];
    }

    /** @param array<string, mixed> $checkpoint */
    private function saveCheckpoint(CatalogSource $source, string $mode, array $checkpoint): void
    {
        $settings = $source->settings ?? [];
        $checkpoints = is_array($settings['reference_checkpoints'] ?? null) ? $settings['reference_checkpoints'] : [];
        $checkpoints[$mode] = $checkpoint;
        $settings['reference_checkpoints'] = $checkpoints;
        $source->settings = $settings;
        $source->save();
    }

    private function clearCheckpoint(CatalogSource $source, string $mode): void
    {
        $settings = $source->settings ?? [];
        $checkpoints = is_array($settings['reference_checkpoints'] ?? null) ? $settings['reference_checkpoints'] : [];
        unset($checkpoints[$mode]);
        $settings['reference_checkpoints'] = $checkpoints;
        $source->settings = $settings;
        $source->save();
    }

    private function apiBaseUrl(CatalogSource $source): string
    {
        $url = (string) ($source->settings['api_base_url'] ?? 'https://vpic.nhtsa.dot.gov');
        $parts = parse_url($url);
        if (($parts['scheme'] ?? null) !== 'https' || strtolower((string) ($parts['host'] ?? '')) !== 'vpic.nhtsa.dot.gov') {
            throw new RuntimeException('vPIC API base URL must be https://vpic.nhtsa.dot.gov.');
        }

        return rtrim($url, '/');
    }

    /** @param array<string, mixed> $row */
    private function int(array $row, array $keys): ?int
    {
        foreach ($keys as $key) {
            $value = $row[$key] ?? null;
            if (is_numeric($value) && (int) $value > 0) {
                return (int) $value;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $row */
    private function string(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = trim((string) ($row[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }
}
