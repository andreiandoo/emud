<?php

namespace Database\Seeders;

use App\Catalog\Normalization\IdentifierNormalizer;
use App\Models\Brand;
use App\Models\CatalogFitment;
use App\Models\CatalogFitmentConstraint;
use App\Models\CatalogPart;
use App\Models\CatalogPartNumber;
use App\Models\CatalogPartRelation;
use App\Models\CatalogSource;
use App\Models\CatalogSourceAssertion;
use App\Models\CatalogSourceRecord;
use App\Models\CatalogUnresolvedPartRelation;
use App\Models\Category;
use App\Models\Supplier;
use App\Models\SupplierOffer;
use App\Models\SupplierProduct;
use App\Models\SupplierProductMatchCandidate;
use App\Models\VehicleConfiguration;
use App\Models\VehicleEngine;
use App\Models\VehicleGeneration;
use App\Models\VehicleIdentifier;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use App\Models\VehicleTechnicalFact;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AutomotiveCatalogDemoSeeder extends Seeder
{
    private IdentifierNormalizer $normalizer;

    /** @var array<string, CatalogSource> */
    private array $sources = [];

    public function run(): void
    {
        $this->normalizer = app(IdentifierNormalizer::class);
        $this->call(CategorySeeder::class);

        DB::transaction(function (): void {
            $this->seedSources();
            $vehicles = $this->seedVehicles();
            $parts = $this->seedParts();
            $this->seedRelations($parts);
            $this->seedFitments($vehicles, $parts);
            $this->seedSupplier($parts);
        });
    }

    private function seedSources(): void
    {
        $this->sources['public'] = $this->source('DEMO_PUBLIC', [
            'name' => 'DEMO Public Technical Catalog',
            'rights_class' => 'open_redistributable',
            'allow_internal' => true,
            'allow_ecommerce' => true,
            'allow_derived' => true,
            'allow_api_redistribution' => true,
            'allow_bulk_export' => true,
            'legal_notes' => 'Synthetic local-development fixture. Not manufacturer, OEM, TecDoc or supplier truth.',
        ]);

        $this->sources['commerce'] = $this->source('DEMO_COMMERCE_ONLY', [
            'name' => 'DEMO Commerce-only Catalog',
            'rights_class' => 'commerce_only',
            'allow_internal' => true,
            'allow_ecommerce' => true,
            'allow_derived' => false,
            'allow_api_redistribution' => false,
            'allow_bulk_export' => false,
            'legal_notes' => 'Synthetic fixture used to verify ecommerce visibility can be broader than API visibility.',
        ]);

        $this->sources['restricted'] = $this->source('DEMO_RESTRICTED', [
            'name' => 'DEMO Restricted Reference',
            'rights_class' => 'restricted',
            'allow_internal' => true,
            'allow_ecommerce' => false,
            'allow_derived' => false,
            'allow_api_redistribution' => false,
            'allow_bulk_export' => false,
            'legal_notes' => 'Synthetic fixture used exclusively to prove restricted assertions do not leak to the public API.',
        ]);
    }

    /** @return array<string, VehicleConfiguration> */
    private function seedVehicles(): array
    {
        return [
            'duster' => $this->vehicle([
                'make' => 'Dacia', 'model' => 'Duster', 'generation' => 'II (DEMO)', 'year_from' => 2018, 'year_to' => 2024,
                'chassis' => 'DEMO-DUSTER-II', 'engine_name' => '1.5 diesel DEMO', 'engine_code' => 'DEMO-D15',
                'displacement_l' => 1.50, 'displacement_cc' => 1461, 'fuel' => 'diesel', 'power_hp' => 115, 'power_kw' => 84.6,
                'year' => 2019, 'commercial_name' => 'Duster 1.5 4x4 DEMO', 'body' => 'suv', 'drive' => '4x4', 'transmission' => 'manual',
                'fingerprint' => 'demo:dacia:duster:ii:2019:d15:4x4',
                'identifier' => 'DEMO-VEH-DUSTER-2019',
                'facts' => ['ground_clearance_mm' => 210, 'wheel_bolt_pattern' => '5x114.3'],
            ]),
            'hilux' => $this->vehicle([
                'make' => 'Toyota', 'model' => 'Hilux', 'generation' => 'VIII (DEMO)', 'year_from' => 2015, 'year_to' => 2025,
                'chassis' => 'DEMO-HILUX-VIII', 'engine_name' => '2.8 diesel DEMO', 'engine_code' => 'DEMO-H28',
                'displacement_l' => 2.80, 'displacement_cc' => 2755, 'fuel' => 'diesel', 'power_hp' => 204, 'power_kw' => 150.0,
                'year' => 2021, 'commercial_name' => 'Hilux 2.8 4WD DEMO', 'body' => 'pickup', 'drive' => '4x4', 'transmission' => 'automatic',
                'fingerprint' => 'demo:toyota:hilux:viii:2021:h28:4x4',
                'identifier' => 'DEMO-VEH-HILUX-2021',
                'facts' => ['ground_clearance_mm' => 310, 'gross_vehicle_mass_kg' => 3210],
            ]),
            'jimny' => $this->vehicle([
                'make' => 'Suzuki', 'model' => 'Jimny', 'generation' => 'IV (DEMO)', 'year_from' => 2018, 'year_to' => null,
                'chassis' => 'DEMO-JIMNY-IV', 'engine_name' => '1.5 petrol DEMO', 'engine_code' => 'DEMO-J15',
                'displacement_l' => 1.50, 'displacement_cc' => 1462, 'fuel' => 'petrol', 'power_hp' => 102, 'power_kw' => 75.0,
                'year' => 2020, 'commercial_name' => 'Jimny 1.5 4WD DEMO', 'body' => 'suv', 'drive' => '4x4', 'transmission' => 'manual',
                'fingerprint' => 'demo:suzuki:jimny:iv:2020:j15:4x4',
                'identifier' => 'DEMO-VEH-JIMNY-2020',
                'facts' => ['ground_clearance_mm' => 210, 'gross_vehicle_mass_kg' => 1435],
            ]),
            'wrangler' => $this->vehicle([
                'make' => 'Jeep', 'model' => 'Wrangler', 'generation' => 'JL (DEMO)', 'year_from' => 2018, 'year_to' => null,
                'chassis' => 'DEMO-WRANGLER-JL', 'engine_name' => '2.0 petrol DEMO', 'engine_code' => 'DEMO-JL20',
                'displacement_l' => 2.00, 'displacement_cc' => 1995, 'fuel' => 'petrol', 'power_hp' => 272, 'power_kw' => 200.0,
                'year' => 2021, 'commercial_name' => 'Wrangler JL 2.0 4x4 DEMO', 'body' => 'suv', 'drive' => '4x4', 'transmission' => 'automatic',
                'fingerprint' => 'demo:jeep:wrangler:jl:2021:jl20:4x4',
                'identifier' => 'DEMO-VEH-WRANGLER-2021',
                'facts' => ['ground_clearance_mm' => 252, 'wheel_bolt_pattern' => '5x127'],
            ]),
        ];
    }

    /** @return array<string, CatalogPart> */
    private function seedParts(): array
    {
        $public = $this->sources['public'];
        $commerce = $this->sources['commerce'];
        $restricted = $this->sources['restricted'];

        return [
            'oil_filter_a' => $this->part('DemoFilter', 'DEMO-FLT-100', 'Oil filter DEMO 100', 'piese-de-schimb/filtre-ulei', $public, [
                ['scheme' => 'IAM', 'number' => 'DF-100-IAM'], ['scheme' => 'EAN_GTIN', 'number' => '5900000000100'],
            ]),
            'oil_filter_b' => $this->part('DemoFilter', 'DEMO-FLT-110', 'Oil filter equivalent DEMO 110', 'piese-de-schimb/filtre-ulei', $public),
            'oil_filter_new' => $this->part('DemoFilter', 'DEMO-FLT-120', 'Oil filter replacement DEMO 120', 'piese-de-schimb/filtre-ulei', $public),
            'oil_filter_latest' => $this->part('DemoFilter', 'DEMO-FLT-130', 'Oil filter latest DEMO 130', 'piese-de-schimb/filtre-ulei', $public),
            'air_filter' => $this->part('DemoFilter', 'DEMO-AIR-600', 'Air filter DEMO', 'piese-de-schimb/filtre-aer', $public),
            'brake_pads' => $this->part('DemoBrake', 'DEMO-BRK-200', 'Front brake pad set DEMO', 'piese-de-schimb/sistem-de-franare', $public),
            'brake_disc' => $this->part('DemoBrake', 'DEMO-BRK-210', 'Front brake disc DEMO', 'piese-de-schimb/sistem-de-franare', $public),
            'bearing' => $this->part('DemoMotion', 'DEMO-BRG-300', 'Wheel bearing DEMO', 'transmisie/rulmenti', $public),
            'shock' => $this->part('DemoTrail', 'DEMO-SUS-400', 'Heavy-duty shock absorber DEMO', 'suspensie-directie/amortizoare', $public),
            'lift_kit' => $this->part('DemoTrail', 'DEMO-LFT-410', '40 mm suspension lift kit DEMO', 'suspensie-directie/kit-uri-de-inaltare', $public),
            'skid_plate' => $this->part('DemoTrail', 'DEMO-SKD-500', 'Engine skid plate DEMO', 'accesorii-interior-exterior/accesorii-exterior/scuturi-metalice', $public),
            'recovery' => $this->part('DemoTrail', 'DEMO-RCV-510', 'Rated recovery point DEMO', 'trolii-si-recuperare/accesorii-recuperare', $public),
            'commerce_only' => $this->part('DemoCommerce', 'DEMO-ECO-800', 'Commerce-only accessory DEMO', 'accesorii-interior-exterior/accesorii-exterior', $commerce),
            'restricted' => $this->part('DemoRestricted', 'DEMO-PRIVATE-900', 'Restricted reference part DEMO', 'piese-de-schimb', $restricted),
        ];
    }

    /** @param array<string, CatalogPart> $parts */
    private function seedRelations(array $parts): void
    {
        $public = $this->sources['public'];
        $restricted = $this->sources['restricted'];

        $this->relation($parts['oil_filter_a'], $parts['oil_filter_b'], 'equivalent', false, 97, $public);
        $this->relation($parts['oil_filter_a'], $parts['oil_filter_new'], 'superseded_by', true, 92, $public);
        $this->relation($parts['oil_filter_new'], $parts['oil_filter_latest'], 'superseded_by', true, 84, $public);

        // A deliberately restricted bridge. Public graph traversal must never expose the target.
        $this->relation($parts['oil_filter_a'], $parts['restricted'], 'equivalent', false, 100, $restricted);

        $record = $this->sourceRecord($public, 'part', 'DEMO-FLT-100', [
            'mpn' => 'DEMO-FLT-100',
            'cross_references' => [['brand' => 'DemoMissing', 'number' => 'DEMO-MISSING-999']],
        ], $parts['oil_filter_a']);

        CatalogUnresolvedPartRelation::query()->updateOrCreate([
            'source_part_id' => $parts['oil_filter_a']->id,
            'relation_type' => 'equivalent',
            'target_scheme' => 'MPN',
            'target_brand_normalized' => $this->normalizer->normalize('DemoMissing'),
            'target_number_normalized' => $this->normalizer->normalize('DEMO-MISSING-999'),
            'catalog_source_id' => $public->id,
        ], [
            'target_brand_raw' => 'DemoMissing',
            'target_number_raw' => 'DEMO-MISSING-999',
            'target_number_compact' => $this->normalizer->compact('DEMO-MISSING-999'),
            'catalog_source_record_id' => $record->id,
            'confidence' => 76,
            'status' => 'pending',
            'resolution_attempts' => 0,
            'last_resolution_attempt_at' => null,
            'resolved_target_part_id' => null,
            'resolved_at' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'review_note' => null,
            'metadata' => ['fixture' => true, 'reason' => 'Intentional unresolved demo relation for QA testing.'],
        ]);
    }

    /**
     * @param array<string, VehicleConfiguration> $vehicles
     * @param array<string, CatalogPart> $parts
     */
    private function seedFitments(array $vehicles, array $parts): void
    {
        $public = $this->sources['public'];
        $commerce = $this->sources['commerce'];

        $this->fitment($parts['oil_filter_a'], $vehicles['duster'], 'confirmed', 99, $public);
        $this->fitment($parts['oil_filter_b'], $vehicles['duster'], 'confirmed', 97, $public);
        $this->fitment($parts['air_filter'], $vehicles['duster'], 'confirmed', 98, $public);
        $this->fitment($parts['brake_pads'], $vehicles['duster'], 'confirmed', 96, $public, 'front');
        $this->fitment($parts['brake_disc'], $vehicles['duster'], 'confirmed', 96, $public, 'front');
        $this->fitment($parts['bearing'], $vehicles['duster'], 'confirmed', 94, $public, 'front');

        $this->fitment($parts['shock'], $vehicles['hilux'], 'confirmed', 98, $public, 'front');
        $this->fitment($parts['lift_kit'], $vehicles['hilux'], 'conditional', 93, $public, null, [
            'constraint_type' => 'engine_code', 'operator' => 'eq', 'value_text' => 'DEMO-H28',
            'normalized' => ['engine_code' => 'DEMO-H28'], 'display_text' => 'DEMO fixture: requires the H28 test configuration.',
        ]);
        $this->fitment($parts['skid_plate'], $vehicles['hilux'], 'confirmed', 97, $public);
        $this->fitment($parts['recovery'], $vehicles['hilux'], 'conditional', 91, $public, 'front', [
            'constraint_type' => 'gross_vehicle_mass_kg', 'operator' => 'lte', 'value_number' => 3500,
            'unit' => 'kg', 'normalized' => ['max_kg' => 3500], 'display_text' => 'DEMO fixture: rated for configurations up to 3500 kg.',
        ]);

        $this->fitment($parts['lift_kit'], $vehicles['jimny'], 'confirmed', 95, $public);
        $this->fitment($parts['skid_plate'], $vehicles['jimny'], 'confirmed', 90, $public);
        $this->fitment($parts['shock'], $vehicles['wrangler'], 'confirmed', 94, $public, 'rear');
        $this->fitment($parts['recovery'], $vehicles['wrangler'], 'confirmed', 96, $public, 'front');

        $this->fitment($parts['commerce_only'], $vehicles['duster'], 'confirmed', 100, $commerce);

        // Deliberately public fitment evidence pointing at a restricted-identity part.
        // API controllers must still suppress the part because its identity has no public assertion.
        $this->fitment($parts['restricted'], $vehicles['duster'], 'confirmed', 100, $public);
    }

    /** @param array<string, CatalogPart> $parts */
    private function seedSupplier(array $parts): void
    {
        $supplier = Supplier::query()->updateOrCreate(['code' => 'DEMO_DISTRIBUTOR_EU'], [
            'name' => 'DEMO Distributor EU',
            'protocol' => 'manual',
            'connector_class' => null,
            'catalog_endpoint' => null,
            'stock_endpoint' => null,
            'price_endpoint' => null,
            'credentials' => null,
            'field_mapping' => null,
            'settings' => ['fixture' => true, 'technical_promotion_mode' => 'augment_only'],
            'data_rights_class' => 'permissioned_redistributable',
            'allow_internal_data' => true,
            'allow_ecommerce_data' => true,
            'allow_derived_data' => true,
            'allow_api_redistribution' => true,
            'attribution_required' => false,
            'license_name' => 'Synthetic DEMO fixture',
            'legal_notes' => 'Local-development data generated by AutomotiveCatalogDemoSeeder.',
            'default_currency' => 'RON',
            'timezone' => 'Europe/Bucharest',
            'priority' => 1,
            'is_active' => true,
        ]);

        $supplierSource = $this->source('DEMO_SUPPLIER_TECHNICAL', [
            'name' => 'DEMO Distributor Technical Feed',
            'source_type' => 'supplier',
            'supplier_id' => $supplier->id,
            'rights_class' => 'permissioned_redistributable',
            'allow_internal' => true,
            'allow_ecommerce' => true,
            'allow_derived' => true,
            'allow_api_redistribution' => true,
            'allow_bulk_export' => false,
            'legal_notes' => 'Synthetic supplier technical source used for local mapping tests.',
        ]);

        $mapped = [
            ['part' => 'oil_filter_a', 'external' => 'SUP-DEMO-001', 'sku' => 'S-D-FLT100', 'cost' => 31.40, 'rrp' => 59.90, 'stock' => 24],
            ['part' => 'brake_pads', 'external' => 'SUP-DEMO-002', 'sku' => 'S-D-BRK200', 'cost' => 119.00, 'rrp' => 219.90, 'stock' => 8],
            ['part' => 'lift_kit', 'external' => 'SUP-DEMO-003', 'sku' => 'S-D-LFT410', 'cost' => 1180.00, 'rrp' => 1799.00, 'stock' => 3],
            ['part' => 'skid_plate', 'external' => 'SUP-DEMO-004', 'sku' => 'S-D-SKD500', 'cost' => 510.00, 'rrp' => 799.00, 'stock' => 6],
        ];

        foreach ($mapped as $row) {
            $part = $parts[$row['part']];
            $supplierProduct = SupplierProduct::query()->updateOrCreate([
                'supplier_id' => $supplier->id,
                'external_id' => $row['external'],
            ], [
                'catalog_part_id' => $part->id,
                'product_id' => null,
                'variant_id' => null,
                'supplier_sku' => $row['sku'],
                'ean' => null,
                'manufacturer_part_number' => $part->mpn_raw,
                'raw_brand' => $part->brand?->name,
                'name' => $part->name.' supplier offer',
                'source_url' => null,
                'source_hash' => hash('sha256', $row['external']),
                'raw_payload' => ['fixture' => true, 'external_id' => $row['external']],
                'technical_payload' => ['brand' => $part->brand?->name, 'mpn' => $part->mpn_raw],
                'mapping_status' => 'mapped',
                'mapping_confidence' => 100,
                'catalog_mapping_status' => 'auto_mapped',
                'catalog_mapping_reason' => ['exact_demo_mpn' => true],
                'catalog_mapped_at' => now(),
                'technical_promotion_status' => 'promoted',
                'technical_promotion_error' => null,
                'technical_promoted_at' => now(),
                'last_seen_at' => now(),
            ]);

            SupplierOffer::query()->updateOrCreate(['supplier_product_id' => $supplierProduct->id], [
                'cost_price' => $row['cost'],
                'recommended_retail_price' => $row['rrp'],
                'currency' => 'RON',
                'vat_rate' => 21,
                'stock_quantity' => $row['stock'],
                'stock_status' => $row['stock'] > 0 ? 'in_stock' : 'out_of_stock',
                'lead_time_days' => 1,
                'minimum_order_quantity' => 1,
                'price_synced_at' => now(),
                'stock_synced_at' => now(),
                'stale_after' => now()->addDay(),
            ]);

            $this->sourceRecord($supplierSource, 'part', $row['external'], [
                'brand' => $part->brand?->name,
                'mpn' => $part->mpn_raw,
                'supplier_sku' => $row['sku'],
            ], $part, $supplierProduct);
        }

        $unmapped = SupplierProduct::query()->updateOrCreate([
            'supplier_id' => $supplier->id,
            'external_id' => 'SUP-DEMO-UNMAPPED',
        ], [
            'catalog_part_id' => null,
            'product_id' => null,
            'variant_id' => null,
            'supplier_sku' => 'S-D-UNKNOWN',
            'ean' => null,
            'manufacturer_part_number' => 'DEMO-UNKNOWN-777',
            'raw_brand' => 'DemoUnknown',
            'name' => 'Intentional unmatched supplier product DEMO',
            'raw_payload' => ['fixture' => true, 'reason' => 'supplier matching queue demo'],
            'technical_payload' => ['brand' => 'DemoUnknown', 'mpn' => 'DEMO-UNKNOWN-777'],
            'mapping_status' => 'unmapped',
            'mapping_confidence' => null,
            'catalog_mapping_status' => 'unmapped',
            'catalog_mapping_reason' => ['fixture' => 'intentional_no_match'],
            'catalog_mapped_at' => null,
            'technical_promotion_status' => 'awaiting_match',
            'technical_promoted_at' => null,
            'last_seen_at' => now(),
        ]);

        SupplierOffer::query()->updateOrCreate(['supplier_product_id' => $unmapped->id], [
            'cost_price' => 99.00,
            'recommended_retail_price' => 169.00,
            'currency' => 'RON',
            'vat_rate' => 21,
            'stock_quantity' => 11,
            'stock_status' => 'in_stock',
            'lead_time_days' => 2,
            'minimum_order_quantity' => 1,
            'price_synced_at' => now(),
            'stock_synced_at' => now(),
            'stale_after' => now()->addDay(),
        ]);

        SupplierProductMatchCandidate::query()->updateOrCreate([
            'supplier_product_id' => $unmapped->id,
            'catalog_part_id' => $parts['air_filter']->id,
        ], [
            'score' => 42,
            'reasons' => ['fixture' => true, 'reason' => 'weak synthetic candidate for manual review'],
            'status' => 'candidate',
        ]);
    }

    private function source(string $code, array $values): CatalogSource
    {
        $source = CatalogSource::query()->firstOrNew(['code' => $code]);
        if (! $source->exists) {
            $source->public_id = (string) Str::ulid();
        }

        $source->fill(array_merge([
            'source_type' => 'demo',
            'protocol' => 'manual',
            'connector_class' => null,
            'canonicalizer_class' => null,
            'credentials' => null,
            'settings' => ['fixture' => true],
            'field_mapping' => null,
            'allow_internal' => true,
            'allow_ecommerce' => false,
            'allow_derived' => false,
            'allow_api_redistribution' => false,
            'allow_bulk_export' => false,
            'allow_media_redistribution' => false,
            'attribution_required' => false,
            'territories' => ['RO', 'EU'],
            'capabilities' => ['demo_fixture'],
            'is_active' => true,
        ], $values));
        $source->save();

        return $source;
    }

    private function vehicle(array $data): VehicleConfiguration
    {
        $source = $this->sources['public'];
        $make = VehicleMake::query()->updateOrCreate(['slug' => Str::slug($data['make'])], [
            'name' => $data['make'], 'is_active' => true,
        ]);
        $model = VehicleModel::query()->updateOrCreate(['make_id' => $make->id, 'slug' => Str::slug($data['model'])], [
            'name' => $data['model'], 'is_active' => true,
        ]);
        $generation = VehicleGeneration::query()->firstOrCreate([
            'model_id' => $model->id,
            'name' => $data['generation'],
            'year_from' => $data['year_from'],
        ], [
            'year_to' => $data['year_to'],
            'chassis_code' => $data['chassis'],
            'metadata' => ['fixture' => true],
            'production_from' => $data['year_from'].'-01-01',
            'production_to' => $data['year_to'] ? $data['year_to'].'-12-31' : null,
        ]);
        $engine = VehicleEngine::query()->updateOrCreate([
            'generation_id' => $generation->id,
            'engine_code' => $data['engine_code'],
        ], [
            'name' => $data['engine_name'],
            'manufacturer_name' => $data['make'].' DEMO',
            'family' => 'DEMO',
            'displacement_l' => $data['displacement_l'],
            'displacement_cc' => $data['displacement_cc'],
            'fuel_type' => $data['fuel'],
            'power_hp' => $data['power_hp'],
            'power_kw' => $data['power_kw'],
        ]);

        $configuration = VehicleConfiguration::query()->updateOrCreate([
            'canonical_fingerprint' => hash('sha256', $data['fingerprint']),
        ], [
            'generation_id' => $generation->id,
            'engine_id' => $engine->id,
            'year' => $data['year'],
            'body_type' => $data['body'],
            'drive_type' => $data['drive'],
            'transmission' => $data['transmission'],
            'commercial_name' => $data['commercial_name'],
            'model_year_from' => $data['year'],
            'model_year_to' => $data['year'],
            'production_from' => $data['year'].'-01-01',
            'production_to' => $data['year'].'-12-31',
            'market' => 'EU',
            'fuel_type' => $data['fuel'],
            'displacement_cc' => $data['displacement_cc'],
            'power_kw' => $data['power_kw'],
            'quality_score' => 100,
            'metadata' => ['fixture' => true, 'authoritative' => false],
        ]);

        VehicleIdentifier::query()->updateOrCreate([
            'configuration_id' => $configuration->id,
            'scheme' => 'DEMO_VEHICLE_ID',
            'namespace' => 'emud_demo',
            'value_normalized' => $this->normalizer->normalize($data['identifier']),
        ], [
            'value_raw' => $data['identifier'],
            'catalog_source_id' => $source->id,
            'confidence' => 100,
        ]);

        foreach ($data['facts'] as $fact => $value) {
            VehicleTechnicalFact::query()->updateOrCreate([
                'configuration_id' => $configuration->id,
                'fact_key' => $fact,
                'catalog_source_id' => $source->id,
            ], is_numeric($value) ? [
                'value_numeric' => $value,
                'unit' => str_ends_with($fact, '_mm') ? 'mm' : (str_ends_with($fact, '_kg') ? 'kg' : null),
                'confidence' => 100,
            ] : [
                'value_text' => (string) $value,
                'confidence' => 100,
            ]);
        }

        $record = $this->sourceRecord($source, 'vehicle_configuration', $data['identifier'], [
            'make' => $data['make'], 'model' => $data['model'], 'configuration' => $data['commercial_name'],
        ], $configuration, null, 'vehicle_configuration');
        $this->assertion($source, 'vehicle_configuration', $configuration->id, $record, true);

        return $configuration;
    }

    private function part(
        string $brandName,
        string $mpn,
        string $name,
        string $categoryPath,
        CatalogSource $source,
        array $extraNumbers = [],
    ): CatalogPart {
        $brand = Brand::query()->updateOrCreate(['slug' => Str::slug($brandName)], [
            'name' => $brandName, 'is_active' => true,
        ]);
        $category = Category::query()->where('full_path', $categoryPath)->first();
        $normalized = $this->normalizer->normalize($mpn);

        $part = CatalogPart::query()->firstOrNew(['brand_id' => $brand->id, 'mpn_normalized' => $normalized]);
        if (! $part->exists) {
            $part->public_id = (string) Str::ulid();
        }
        $part->fill([
            'category_id' => $category?->id,
            'mpn_raw' => $mpn,
            'name' => $name,
            'description' => 'Synthetic DEMO catalog item. It exists only for local development and automated tests.',
            'lifecycle_status' => 'active',
            'quality_score' => 100,
            'metadata' => ['fixture' => true, 'authoritative' => false],
        ])->save();

        $record = $this->sourceRecord($source, 'part', $mpn, [
            'brand' => $brandName, 'mpn' => $mpn, 'name' => $name,
        ], $part);

        $this->partNumber($part, $brand, 'MPN', $mpn, $source, $record);
        foreach ($extraNumbers as $number) {
            $this->partNumber($part, $brand, $number['scheme'], $number['number'], $source, $record);
        }

        $this->assertion($source, 'catalog_part', $part->id, $record, $source->allow_api_redistribution);

        return $part->load('brand');
    }

    private function partNumber(
        CatalogPart $part,
        Brand $brand,
        string $scheme,
        string $number,
        CatalogSource $source,
        CatalogSourceRecord $record,
    ): void {
        CatalogPartNumber::query()->updateOrCreate([
            'catalog_part_id' => $part->id,
            'scheme' => strtoupper($scheme),
            'number_normalized' => $this->normalizer->normalize($number),
            'catalog_source_id' => $source->id,
        ], [
            'brand_id' => $brand->id,
            'namespace' => 'emud_demo',
            'number_raw' => $number,
            'number_compact' => $this->normalizer->compact($number),
            'catalog_source_record_id' => $record->id,
            'confidence' => 100,
        ]);
    }

    private function relation(
        CatalogPart $sourcePart,
        CatalogPart $targetPart,
        string $type,
        bool $directed,
        float $confidence,
        CatalogSource $source,
    ): void {
        CatalogPartRelation::query()->updateOrCreate([
            'source_part_id' => $sourcePart->id,
            'target_part_id' => $targetPart->id,
            'relation_type' => $type,
            'catalog_source_id' => $source->id,
        ], [
            'is_directed' => $directed,
            'confidence' => $confidence,
            'notes' => 'Synthetic DEMO relation.',
        ]);
    }

    private function fitment(
        CatalogPart $part,
        VehicleConfiguration $vehicle,
        string $status,
        float $confidence,
        CatalogSource $source,
        ?string $position = null,
        ?array $constraint = null,
    ): void {
        $fitment = CatalogFitment::query()->updateOrCreate([
            'catalog_part_id' => $part->id,
            'configuration_id' => $vehicle->id,
            'position' => $position,
            'catalog_source_id' => $source->id,
            'source_fitment_key' => 'DEMO:'.$part->id.':'.$vehicle->id.':'.($position ?? 'any'),
        ], [
            'category_id' => $part->category_id,
            'status' => $status,
            'confidence' => $confidence,
        ]);

        if ($constraint) {
            CatalogFitmentConstraint::query()->updateOrCreate([
                'catalog_fitment_id' => $fitment->id,
                'constraint_type' => $constraint['constraint_type'],
                'catalog_source_id' => $source->id,
            ], [
                'operator' => $constraint['operator'] ?? null,
                'value_text' => $constraint['value_text'] ?? null,
                'value_number' => $constraint['value_number'] ?? null,
                'unit' => $constraint['unit'] ?? null,
                'normalized' => $constraint['normalized'] ?? null,
                'display_text' => $constraint['display_text'] ?? null,
            ]);
        }
    }

    private function sourceRecord(
        CatalogSource $source,
        string $recordType,
        string $externalId,
        array $payload,
        object $entity,
        ?SupplierProduct $supplierProduct = null,
        string $entityType = 'catalog_part',
    ): CatalogSourceRecord {
        return CatalogSourceRecord::query()->updateOrCreate([
            'catalog_source_id' => $source->id,
            'record_type' => $recordType,
            'external_id' => $externalId,
        ], [
            'raw_payload' => array_merge(['fixture' => true], $payload),
            'normalized_payload' => $payload,
            'mapping_status' => 'published',
            'canonical_entity_type' => $entityType,
            'canonical_entity_id' => $entity->id,
            'mapping_confidence' => 100,
            'supplier_product_id' => $supplierProduct?->id,
            'last_seen_at' => now(),
        ]);
    }

    private function assertion(
        CatalogSource $source,
        string $entityType,
        int $entityId,
        CatalogSourceRecord $record,
        bool $apiRedistributable,
    ): void {
        CatalogSourceAssertion::query()->updateOrCreate([
            'catalog_source_id' => $source->id,
            'catalog_source_record_id' => $record->id,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'field_or_relation' => 'identity',
        ], [
            'confidence' => 100,
            'status' => 'published',
            'ecommerce_displayable' => $source->allow_ecommerce,
            'api_redistributable' => $apiRedistributable,
        ]);
    }
}
