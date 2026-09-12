<?php

namespace Tests\Concerns;

use App\Catalog\Normalization\IdentifierNormalizer;
use App\Models\Brand;
use App\Models\CatalogApiConsumer;
use App\Models\CatalogApiKey;
use App\Models\CatalogFitment;
use App\Models\CatalogPart;
use App\Models\CatalogPartNumber;
use App\Models\CatalogSource;
use App\Models\CatalogSourceAssertion;
use App\Models\VehicleConfiguration;
use App\Models\VehicleEngine;
use App\Models\VehicleGeneration;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use Illuminate\Support\Str;

/**
 * The smallest graph the public API can answer from: a source with rights, a published entity,
 * and an assertion tying them together. Every one of those three is load-bearing — drop any and
 * the entity correctly disappears from the API — so tests build them explicitly.
 */
trait BuildsCatalogApiFixtures
{
    protected function apiSource(string $code = 'PUBLIC', bool $api = true, bool $attribution = true): CatalogSource
    {
        return CatalogSource::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => $code.' source',
            'code' => $code,
            'source_type' => 'manufacturer',
            'protocol' => 'manual',
            'rights_class' => $api ? 'open_redistributable' : 'internal_reference',
            'allow_internal' => true,
            'allow_ecommerce' => true,
            'allow_derived' => true,
            'allow_api_redistribution' => $api,
            'attribution_required' => $attribution,
            'license_name' => $api ? 'CC BY 4.0' : 'Proprietary',
            'license_url' => 'https://example.test/licence/'.Str::lower($code),
            'is_active' => true,
        ]);
    }

    protected function apiToken(int $quota = 10000): string
    {
        $consumer = CatalogApiConsumer::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'Fixture consumer',
            'slug' => 'fixture-'.Str::lower(Str::random(8)),
            'plan' => 'test',
            'monthly_quota' => $quota,
            'requests_used' => 0,
            'period_started_at' => now()->startOfMonth(),
            'is_active' => true,
        ]);

        return CatalogApiKey::issue($consumer, 'Fixture')['token'];
    }

    protected function publish(CatalogSource $source, string $entityType, int $entityId): CatalogSourceAssertion
    {
        return CatalogSourceAssertion::query()->create([
            'catalog_source_id' => $source->id,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'field_or_relation' => 'identity',
            'status' => 'published',
            'confidence' => 100,
            'ecommerce_displayable' => true,
            'api_redistributable' => (bool) $source->allow_api_redistribution,
        ]);
    }

    protected function apiPart(string $brandName, string $mpn, CatalogSource $source, ?int $categoryId = null): CatalogPart
    {
        $normalizer = app(IdentifierNormalizer::class);
        $brand = Brand::query()->firstOrCreate(
            ['slug' => Str::slug($brandName)],
            ['name' => $brandName, 'is_active' => true],
        );

        $part = CatalogPart::query()->create([
            'public_id' => (string) Str::ulid(),
            'brand_id' => $brand->id,
            'category_id' => $categoryId,
            'mpn_raw' => $mpn,
            'mpn_normalized' => $normalizer->normalize($mpn),
            'name' => $brandName.' '.$mpn,
            'lifecycle_status' => 'active',
            'quality_score' => 100,
        ]);

        CatalogPartNumber::query()->create([
            'catalog_part_id' => $part->id,
            'brand_id' => $brand->id,
            'scheme' => 'MPN',
            'number_raw' => $mpn,
            'number_normalized' => $normalizer->normalize($mpn),
            'number_compact' => $normalizer->compact($mpn),
            'catalog_source_id' => $source->id,
            'confidence' => 100,
        ]);

        $this->publish($source, 'catalog_part', $part->id);

        return $part;
    }

    /**
     * A whole make → model → generation → configuration branch, published end to end.
     */
    protected function apiVehicle(
        CatalogSource $source,
        string $makeName = 'Land Rover',
        string $modelName = 'Defender',
        string $generationName = 'L663',
        int $year = 2022,
    ): VehicleConfiguration {
        $make = VehicleMake::query()->firstOrCreate(
            ['slug' => Str::slug($makeName)],
            ['name' => $makeName, 'is_active' => true],
        );
        $model = VehicleModel::query()->firstOrCreate(
            ['make_id' => $make->id, 'slug' => Str::slug($modelName)],
            ['name' => $modelName, 'is_active' => true],
        );
        $generation = VehicleGeneration::query()->firstOrCreate(
            ['model_id' => $model->id, 'name' => $generationName],
            ['year_from' => $year - 2, 'year_to' => $year + 4],
        );
        $engine = VehicleEngine::query()->firstOrCreate(
            ['generation_id' => $generation->id, 'name' => 'D250'],
            ['engine_code' => 'AJ20D6', 'fuel_type' => 'diesel', 'displacement_l' => 3.0, 'power_hp' => 249],
        );

        $configuration = VehicleConfiguration::query()->create([
            'generation_id' => $generation->id,
            'engine_id' => $engine->id,
            'year' => $year,
            'body_type' => 'suv',
            'drive_type' => '4x4',
            'commercial_name' => "{$makeName} {$modelName} {$generationName} {$year}",
            'fuel_type' => 'diesel',
            'power_kw' => 183,
            'displacement_cc' => 2996,
            'market' => 'EU',
            'quality_score' => 90,
        ]);

        $this->publish($source, 'vehicle_configuration', $configuration->id);

        return $configuration;
    }

    protected function apiFitment(CatalogPart $part, VehicleConfiguration $vehicle, CatalogSource $source, ?string $position = null): CatalogFitment
    {
        return CatalogFitment::query()->create([
            'catalog_part_id' => $part->id,
            'configuration_id' => $vehicle->id,
            'position' => $position,
            'status' => 'confirmed',
            'confidence' => 95,
            'catalog_source_id' => $source->id,
        ]);
    }
}
