<?php

namespace Tests\Feature;

use App\Catalog\Canonicalization\Vehicles\VpicReferenceCanonicalizer;
use App\Models\CatalogImportRun;
use App\Models\CatalogSource;
use App\Models\CatalogSourceRecord;
use App\Models\VehicleMake;
use App\Models\VehicleManufacturer;
use App\Models\VehicleModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class VpicReferenceCanonicalizerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_the_vpic_manufacturer_make_model_and_reference_graph_idempotently(): void
    {
        $source = CatalogSource::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'NHTSA vPIC',
            'code' => 'VPIC',
            'source_type' => 'government_vehicle_database',
            'rights_class' => 'public_information',
            'allow_internal' => true,
            'allow_ecommerce' => true,
            'allow_derived' => true,
            'allow_api_redistribution' => true,
            'is_active' => true,
        ]);
        $run = CatalogImportRun::query()->create([
            'uuid' => (string) Str::uuid(),
            'catalog_source_id' => $source->id,
            'mode' => 'catalog',
            'status' => 'running',
        ]);
        $canonicalizer = app(VpicReferenceCanonicalizer::class);

        $manufacturer = $this->record($source, $run, 'vpic_manufacturer', 'vpic:manufacturer:988', [
            'vpic_manufacturer_id' => 988,
            'manufacturer_name' => 'AMERICAN HONDA MOTOR CO., INC.',
            'Country' => 'UNITED STATES',
            'StateProvince' => 'CA',
            'City' => 'TORRANCE',
            'Mfr_Types' => 'Completed Vehicle Manufacturer',
        ]);
        $make = $this->record($source, $run, 'vpic_make', 'vpic:make:474', [
            'vpic_make_id' => 474,
            'make_name' => 'HONDA',
        ]);
        $model = $this->record($source, $run, 'vpic_model', 'vpic:model:1861', [
            'vpic_make_id' => 474,
            'make_name' => 'HONDA',
            'vpic_model_id' => 1861,
            'model_name' => 'PILOT',
        ]);

        $manufacturerResult = $canonicalizer->canonicalize($manufacturer);
        $makeResult = $canonicalizer->canonicalize($make);
        $modelResult = $canonicalizer->canonicalize($model);

        $this->assertSame('published', $manufacturerResult->status);
        $this->assertSame('published', $makeResult->status);
        $this->assertSame('published', $modelResult->status);
        $this->assertSame(1, VehicleManufacturer::query()->count());
        $this->assertSame(1, VehicleMake::query()->count());
        $this->assertSame(1, VehicleModel::query()->count());

        $relation = $this->record($source, $run, 'vpic_make_manufacturer', 'vpic:make_manufacturer:988:474', [
            'vpic_manufacturer_id' => 988,
            'vpic_make_id' => 474,
            'make_name' => 'HONDA',
            'FromYear' => 2000,
        ]);
        $type = $this->record($source, $run, 'vpic_make_vehicle_type', 'vpic:make_type:474:2', [
            'vpic_make_id' => 474,
            'vehicle_type' => 'Passenger Car',
            'vehicle_type_id' => 2,
        ]);
        $year = $this->record($source, $run, 'vpic_model_year', 'vpic:model_year:1861:2025:any', [
            'vpic_make_id' => 474,
            'make_name' => 'HONDA',
            'vpic_model_id' => 1861,
            'model_name' => 'PILOT',
            'model_year' => 2025,
            'vehicle_type' => 'Multipurpose Passenger Vehicle (MPV)',
        ]);
        $wmi = $this->record($source, $run, 'vpic_wmi', 'vpic:wmi:988:5FN:7', [
            'vpic_manufacturer_id' => 988,
            'vpic_make_id' => 474,
            'wmi' => '5FN',
            'vehicle_type' => 'Multipurpose Passenger Vehicle (MPV)',
            'vehicle_type_id' => 7,
        ]);

        foreach ([$relation, $type, $year, $wmi] as $record) {
            $this->assertSame('published', $canonicalizer->canonicalize($record)->status);
        }

        $this->assertSame(1, DB::table('vehicle_make_manufacturers')->count());
        $this->assertSame(1, DB::table('vehicle_make_types')->count());
        $this->assertSame(1, DB::table('vehicle_model_years')->count());
        $this->assertSame(1, DB::table('vehicle_wmis')->count());
        $this->assertSame(4, DB::table('vehicle_entity_identifiers')->whereIn('scheme', [
            'vpic_manufacturer_id',
            'vpic_make_id',
            'vpic_model_id',
            'wmi',
        ])->count());

        foreach ([$manufacturer, $make, $model, $relation, $type, $year, $wmi] as $record) {
            $canonicalizer->canonicalize($record);
        }

        $this->assertSame(1, VehicleManufacturer::query()->count());
        $this->assertSame(1, VehicleMake::query()->count());
        $this->assertSame(1, VehicleModel::query()->count());
        $this->assertSame(1, DB::table('vehicle_make_manufacturers')->count());
        $this->assertSame(1, DB::table('vehicle_make_types')->count());
        $this->assertSame(1, DB::table('vehicle_model_years')->count());
        $this->assertSame(1, DB::table('vehicle_wmis')->count());
    }

    private function record(CatalogSource $source, CatalogImportRun $run, string $type, string $externalId, array $payload): CatalogSourceRecord
    {
        return CatalogSourceRecord::query()->create([
            'catalog_source_id' => $source->id,
            'catalog_import_run_id' => $run->id,
            'record_type' => $type,
            'external_id' => $externalId,
            'raw_payload' => $payload,
        ]);
    }
}
