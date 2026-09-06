<?php

namespace Tests\Feature;

use App\Catalog\Canonicalization\Vehicles\EeaVehicleCanonicalizer;
use App\Models\CatalogImportRun;
use App\Models\CatalogSource;
use App\Models\CatalogSourceRecord;
use App\Models\VehicleConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EeaVehicleCanonicalizerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_uses_year_instead_of_registration_count_and_keeps_eu_tvv_data(): void
    {
        $source = CatalogSource::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'EEA',
            'code' => 'EEA',
            'source_type' => 'open_vehicle_dataset',
            'rights_class' => 'open_redistributable',
            'allow_internal' => true,
            'allow_ecommerce' => true,
            'allow_derived' => true,
            'allow_api_redistribution' => true,
            'is_active' => true,
        ]);
        $run = CatalogImportRun::query()->create([
            'uuid' => (string) Str::uuid(),
            'catalog_source_id' => $source->id,
            'mode' => 'cars',
            'status' => 'running',
        ]);
        $record = CatalogSourceRecord::query()->create([
            'catalog_source_id' => $source->id,
            'catalog_import_run_id' => $run->id,
            'record_type' => 'vehicle_configuration',
            'external_id' => 'eea:test',
            'raw_payload' => [
                'Mk' => 'LAND ROVER',
                'Cn' => 'DISCOVERY SPORT',
                'Tan' => 'E11*2007/46*4317*12',
                'T' => 'LC',
                'Va' => 'A5',
                'Ve' => 'ABCD',
                'Ct' => 'M1',
                'Cr' => 'M1',
                'Ft' => 'diesel',
                'Fm' => 'M',
                'ec' => 1999,
                'ep' => 132,
                'mass_kg' => 1900,
                'co2_wltp' => 194,
                'r' => 1,
                'year' => 2025,
                'source_vehicle_kind' => 'cars',
                'source_table' => '[CO2Emission].[latest].[co2cars_2025Pv31]',
                'source_status' => 'P',
            ],
        ]);

        $result = app(EeaVehicleCanonicalizer::class)->canonicalize($record);

        $this->assertSame('published', $result->status);
        $this->assertSame('vehicle_configuration', $result->entityType);

        $configuration = VehicleConfiguration::query()->findOrFail($result->entityId);
        $this->assertSame(2025, $configuration->year);
        $this->assertSame('E11*2007/46*4317*12', $configuration->eu_type_approval);
        $this->assertSame('LC', $configuration->eu_type);
        $this->assertSame('A5', $configuration->eu_variant);
        $this->assertSame('ABCD', $configuration->eu_version);
        $this->assertSame(1999, $configuration->displacement_cc);
        $this->assertSame(132.0, (float) $configuration->power_kw);
        $this->assertSame('M1', $configuration->metadata['vehicle_category']);
        $this->assertSame(194.0, (float) $configuration->metadata['co2_wltp_g_km']);
        $this->assertSame('[CO2Emission].[latest].[co2cars_2025Pv31]', $configuration->metadata['source_table']);
    }
}
