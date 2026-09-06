<?php

namespace App\Catalog\Canonicalization\Vehicles;

use App\Catalog\Canonicalization\CanonicalizationResult;
use App\Catalog\Canonicalization\Contracts\CatalogRecordCanonicalizer;
use App\Catalog\Canonicalization\SourceAssertionWriter;
use App\Catalog\Normalization\IdentifierNormalizer;
use App\Models\CatalogSourceRecord;
use App\Models\VehicleMake;
use App\Models\VehicleManufacturer;
use App\Models\VehicleModel;
use Illuminate\Support\Facades\DB;

class VpicReferenceCanonicalizer implements CatalogRecordCanonicalizer
{
    public function __construct(
        private readonly IdentifierNormalizer $normalizer,
        private readonly SourceAssertionWriter $assertions,
    ) {}

    public function canonicalize(CatalogSourceRecord $record): CanonicalizationResult
    {
        return match ($record->record_type) {
            'vpic_manufacturer' => $this->manufacturer($record),
            'vpic_make' => $this->make($record),
            'vpic_model' => $this->model($record),
            'vpic_make_manufacturer' => $this->makeManufacturer($record),
            'vpic_wmi' => $this->wmi($record),
            'vpic_make_vehicle_type' => $this->vehicleType($record),
            'vpic_model_year' => $this->modelYear($record),
            default => CanonicalizationResult::skipped("Unsupported vPIC reference record type: {$record->record_type}."),
        };
    }

    private function manufacturer(CatalogSourceRecord $record): CanonicalizationResult
    {
        $row = $record->raw_payload ?? [];
        $id = $this->integer($row, ['vpic_manufacturer_id', 'Mfr_ID', 'MfrId', 'ManufacturerId']);
        $name = $this->text($row, ['manufacturer_name', 'Mfr_Name', 'MfrName', 'ManufacturerName']);
        if (! $id || ! $name) {
            return CanonicalizationResult::skipped('vPIC manufacturer requires manufacturer ID and name.');
        }

        $manufacturer = VehicleManufacturer::query()->firstOrCreate(
            ['slug' => $this->normalizer->slug($name)],
            [
                'name' => $name,
                'country' => $this->text($row, ['Country']),
                'state' => $this->text($row, ['StateProvince', 'State']),
                'city' => $this->text($row, ['City']),
                'address' => $this->text($row, ['Address', 'Address2']),
                'postal_code' => $this->text($row, ['PostalCode']),
                'manufacturer_types' => $this->listValue($row['Mfr_Types'] ?? $row['ManufacturerTypes'] ?? null),
                'metadata' => ['origin' => 'NHTSA vPIC'],
                'is_active' => true,
            ],
        );

        $manufacturer->fill(array_filter([
            'country' => $this->text($row, ['Country']),
            'state' => $this->text($row, ['StateProvince', 'State']),
            'city' => $this->text($row, ['City']),
            'postal_code' => $this->text($row, ['PostalCode']),
        ], static fn (mixed $value): bool => $value !== null && $value !== ''))->save();

        $this->identifier($record, 'vehicle_manufacturer', $manufacturer->id, 'vpic_manufacturer_id', (string) $id, 100);
        $this->assertions->write($record, 'vehicle_manufacturer', $manufacturer->id, [
            'name' => $name,
            'vpic_manufacturer_id' => $id,
            'country' => $manufacturer->country,
            'state' => $manufacturer->state,
            'city' => $manufacturer->city,
        ], 100);

        return CanonicalizationResult::published('vehicle_manufacturer', $manufacturer->id, 100);
    }

    private function make(CatalogSourceRecord $record): CanonicalizationResult
    {
        $row = $record->raw_payload ?? [];
        $id = $this->integer($row, ['vpic_make_id', 'Make_ID', 'MakeId']);
        $name = $this->text($row, ['make_name', 'Make_Name', 'MakeName']);
        if (! $id || ! $name) {
            return CanonicalizationResult::skipped('vPIC make requires make ID and name.');
        }

        $make = VehicleMake::query()->firstOrCreate(
            ['slug' => $this->normalizer->slug($name)],
            ['name' => $name, 'is_active' => true],
        );
        $this->identifier($record, 'vehicle_make', $make->id, 'vpic_make_id', (string) $id, 100);
        $this->alias($record, 'vehicle_make', $make->id, $name, 'US');
        $this->assertions->write($record, 'vehicle_make', $make->id, ['name' => $name, 'vpic_make_id' => $id], 100);

        return CanonicalizationResult::published('vehicle_make', $make->id, 100);
    }

    private function model(CatalogSourceRecord $record): CanonicalizationResult
    {
        $row = $record->raw_payload ?? [];
        $makeId = $this->integer($row, ['vpic_make_id', 'Make_ID', 'MakeId']);
        $makeName = $this->text($row, ['make_name', 'Make_Name', 'MakeName']);
        $modelId = $this->integer($row, ['vpic_model_id', 'Model_ID', 'ModelId']);
        $modelName = $this->text($row, ['model_name', 'Model_Name', 'ModelName']);
        if (! $makeId || ! $makeName || ! $modelId || ! $modelName) {
            return CanonicalizationResult::skipped('vPIC model requires make and model IDs/names.');
        }

        $make = $this->makeByVpicId($record, $makeId) ?? VehicleMake::query()->firstOrCreate(
            ['slug' => $this->normalizer->slug($makeName)],
            ['name' => $makeName, 'is_active' => true],
        );
        $this->identifier($record, 'vehicle_make', $make->id, 'vpic_make_id', (string) $makeId, 100);

        $model = VehicleModel::query()->firstOrCreate(
            ['make_id' => $make->id, 'slug' => $this->normalizer->slug($modelName)],
            ['name' => $modelName, 'is_active' => true],
        );
        $this->identifier($record, 'vehicle_model', $model->id, 'vpic_model_id', (string) $modelId, 100);
        $this->alias($record, 'vehicle_model', $model->id, $modelName, 'US');
        $this->assertions->write($record, 'vehicle_model', $model->id, [
            'make' => $makeName,
            'model' => $modelName,
            'vpic_make_id' => $makeId,
            'vpic_model_id' => $modelId,
        ], 100);

        return CanonicalizationResult::published('vehicle_model', $model->id, 100);
    }

    private function makeManufacturer(CatalogSourceRecord $record): CanonicalizationResult
    {
        $row = $record->raw_payload ?? [];
        $makeId = $this->integer($row, ['vpic_make_id', 'Make_ID', 'MakeId']);
        $manufacturerId = $this->integer($row, ['vpic_manufacturer_id', 'Mfr_ID', 'MfrId', 'ManufacturerId']);
        if (! $makeId || ! $manufacturerId) {
            return CanonicalizationResult::skipped('vPIC make/manufacturer relation requires both source IDs.');
        }

        $make = $this->makeByVpicId($record, $makeId);
        $manufacturer = $this->manufacturerByVpicId($record, $manufacturerId);
        if (! $make || ! $manufacturer) {
            return CanonicalizationResult::ambiguous('Canonicalize vPIC catalog makes/manufacturers before manufacturer_links.', 50);
        }

        DB::table('vehicle_make_manufacturers')->updateOrInsert(
            [
                'make_id' => $make->id,
                'manufacturer_id' => $manufacturer->id,
                'catalog_source_id' => $record->catalog_source_id,
            ],
            [
                'year_from' => $this->integer($row, ['FromYear', 'YearFrom']),
                'year_to' => $this->integer($row, ['ToYear', 'YearTo']),
                'metadata' => json_encode(['origin' => 'NHTSA vPIC'], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
        $this->assertions->write($record, 'vehicle_make', $make->id, ['manufacturer_id' => $manufacturer->id], 100);

        return CanonicalizationResult::published('vehicle_make', $make->id, 100);
    }

    private function wmi(CatalogSourceRecord $record): CanonicalizationResult
    {
        $row = $record->raw_payload ?? [];
        $manufacturerId = $this->integer($row, ['vpic_manufacturer_id', 'Mfr_ID', 'MfrId', 'ManufacturerId']);
        $wmi = $this->text($row, ['wmi', 'WMI', 'Wmi']);
        if (! $manufacturerId || ! $wmi) {
            return CanonicalizationResult::skipped('vPIC WMI requires manufacturer ID and WMI.');
        }

        $manufacturer = $this->manufacturerByVpicId($record, $manufacturerId);
        if (! $manufacturer) {
            return CanonicalizationResult::ambiguous('Canonicalize vPIC manufacturers before WMI records.', 50);
        }

        $makeId = $this->integer($row, ['vpic_make_id', 'Make_ID', 'MakeId']);
        $make = $makeId ? $this->makeByVpicId($record, $makeId) : null;
        DB::table('vehicle_wmis')->updateOrInsert(
            [
                'manufacturer_id' => $manufacturer->id,
                'wmi' => strtoupper($wmi),
                'catalog_source_id' => $record->catalog_source_id,
            ],
            [
                'make_id' => $make?->id,
                'vehicle_type' => $this->text($row, ['vehicle_type', 'VehicleType', 'VehicleTypeName']),
                'metadata' => json_encode(['vehicle_type_id' => $this->integer($row, ['vehicle_type_id', 'VehicleTypeId'])], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
        $this->identifier($record, 'vehicle_manufacturer', $manufacturer->id, 'wmi', strtoupper($wmi), 100);

        return CanonicalizationResult::published('vehicle_manufacturer', $manufacturer->id, 100);
    }

    private function vehicleType(CatalogSourceRecord $record): CanonicalizationResult
    {
        $row = $record->raw_payload ?? [];
        $makeId = $this->integer($row, ['vpic_make_id', 'Make_ID', 'MakeId']);
        $typeName = $this->text($row, ['vehicle_type', 'VehicleTypeName', 'VehicleType']);
        if (! $makeId || ! $typeName) {
            return CanonicalizationResult::skipped('vPIC make vehicle type requires make ID and type name.');
        }
        $make = $this->makeByVpicId($record, $makeId);
        if (! $make) {
            return CanonicalizationResult::ambiguous('Canonicalize vPIC makes before vehicle type records.', 50);
        }

        DB::table('vehicle_make_types')->updateOrInsert(
            [
                'make_id' => $make->id,
                'type_name' => $typeName,
                'catalog_source_id' => $record->catalog_source_id,
            ],
            [
                'type_code' => (string) ($this->integer($row, ['vehicle_type_id', 'VehicleTypeId']) ?? ''),
                'metadata' => json_encode(['origin' => 'NHTSA vPIC'], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        return CanonicalizationResult::published('vehicle_make', $make->id, 100);
    }

    private function modelYear(CatalogSourceRecord $record): CanonicalizationResult
    {
        $row = $record->raw_payload ?? [];
        $makeId = $this->integer($row, ['vpic_make_id', 'Make_ID', 'MakeId']);
        $makeName = $this->text($row, ['make_name', 'Make_Name', 'MakeName']);
        $modelId = $this->integer($row, ['vpic_model_id', 'Model_ID', 'ModelId']);
        $modelName = $this->text($row, ['model_name', 'Model_Name', 'ModelName']);
        $year = $this->integer($row, ['model_year', 'ModelYear']);
        if (! $makeId || ! $makeName || ! $modelId || ! $modelName || ! $year) {
            return CanonicalizationResult::skipped('vPIC model-year requires make/model IDs, names and year.');
        }

        $make = $this->makeByVpicId($record, $makeId) ?? VehicleMake::query()->firstOrCreate(
            ['slug' => $this->normalizer->slug($makeName)],
            ['name' => $makeName, 'is_active' => true],
        );
        $this->identifier($record, 'vehicle_make', $make->id, 'vpic_make_id', (string) $makeId, 100);

        $model = $this->modelByVpicId($record, $modelId) ?? VehicleModel::query()->firstOrCreate(
            ['make_id' => $make->id, 'slug' => $this->normalizer->slug($modelName)],
            ['name' => $modelName, 'is_active' => true],
        );
        $this->identifier($record, 'vehicle_model', $model->id, 'vpic_model_id', (string) $modelId, 100);

        DB::table('vehicle_model_years')->updateOrInsert(
            [
                'model_id' => $model->id,
                'model_year' => $year,
                'vehicle_type' => $this->text($row, ['vehicle_type', 'VehicleTypeName', 'VehicleType']),
                'catalog_source_id' => $record->catalog_source_id,
            ],
            [
                'metadata' => json_encode(['origin' => 'NHTSA vPIC'], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
        $this->assertions->write($record, 'vehicle_model', $model->id, ['model_year' => $year], 100);

        return CanonicalizationResult::published('vehicle_model', $model->id, 100);
    }

    private function identifier(CatalogSourceRecord $record, string $entityType, int $entityId, string $scheme, string $value, float $confidence): void
    {
        DB::table('vehicle_entity_identifiers')->updateOrInsert(
            [
                'entity_type' => $entityType,
                'scheme' => $scheme,
                'value_normalized' => $this->normalizer->normalize($value),
                'catalog_source_id' => $record->catalog_source_id,
            ],
            [
                'entity_id' => $entityId,
                'value_raw' => $value,
                'confidence' => $confidence,
                'metadata' => json_encode(['source_record_id' => $record->id], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    private function alias(CatalogSourceRecord $record, string $entityType, int $entityId, string $alias, ?string $market): void
    {
        DB::table('vehicle_aliases')->updateOrInsert(
            [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'normalized_alias' => $this->normalizer->normalize($alias),
                'market' => $market,
                'catalog_source_id' => $record->catalog_source_id,
            ],
            [
                'alias' => $alias,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    private function makeByVpicId(CatalogSourceRecord $record, int $id): ?VehicleMake
    {
        $entityId = $this->entityId($record, 'vehicle_make', 'vpic_make_id', (string) $id);

        return $entityId ? VehicleMake::query()->find($entityId) : null;
    }

    private function modelByVpicId(CatalogSourceRecord $record, int $id): ?VehicleModel
    {
        $entityId = $this->entityId($record, 'vehicle_model', 'vpic_model_id', (string) $id);

        return $entityId ? VehicleModel::query()->find($entityId) : null;
    }

    private function manufacturerByVpicId(CatalogSourceRecord $record, int $id): ?VehicleManufacturer
    {
        $entityId = $this->entityId($record, 'vehicle_manufacturer', 'vpic_manufacturer_id', (string) $id);

        return $entityId ? VehicleManufacturer::query()->find($entityId) : null;
    }

    private function entityId(CatalogSourceRecord $record, string $entityType, string $scheme, string $value): ?int
    {
        $value = DB::table('vehicle_entity_identifiers')
            ->where('entity_type', $entityType)
            ->where('scheme', $scheme)
            ->where('value_normalized', $this->normalizer->normalize($value))
            ->where('catalog_source_id', $record->catalog_source_id)
            ->value('entity_id');

        return $value ? (int) $value : null;
    }

    /** @param array<string, mixed> $row */
    private function integer(array $row, array $keys): ?int
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
    private function text(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = trim((string) ($row[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /** @return array<int, string>|null */
    private function listValue(mixed $value): ?array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map(static fn (mixed $item): string => trim((string) $item), $value)));
        }
        if (is_string($value) && trim($value) !== '') {
            return array_values(array_filter(array_map('trim', preg_split('/[,;|]/', $value) ?: [])));
        }

        return null;
    }
}
