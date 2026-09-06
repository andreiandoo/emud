<?php

namespace Tests\Feature;

use App\Catalog\Canonicalization\CatalogCanonicalizerRegistry;
use App\Catalog\Normalization\IdentifierNormalizer;
use App\Catalog\Relations\CatalogPartRelationResolver;
use App\Catalog\SupplierPromotion\SupplierTechnicalSourceBridge;
use App\Enums\CatalogRightsClass;
use App\Enums\SupplierProtocol;
use App\Enums\SyncStatus;
use App\Jobs\PromoteSupplierTechnicalData;
use App\Models\Brand;
use App\Models\CatalogConflict;
use App\Models\CatalogPart;
use App\Models\CatalogPartNumber;
use App\Models\CatalogPartRelation;
use App\Models\CatalogSource;
use App\Models\CatalogSourceAssertion;
use App\Models\CatalogSourceRecord;
use App\Models\CatalogUnresolvedPartRelation;
use App\Models\Supplier;
use App\Models\SupplierFeedArtifact;
use App\Models\SupplierProduct;
use App\Models\SupplierSyncRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupplierTechnicalPromotionTest extends TestCase
{
    use RefreshDatabase;

    public function test_promotion_is_blocked_without_derived_data_rights(): void
    {
        $supplier = $this->supplier([
            'allow_derived_data' => false,
            'settings' => ['technical_promotion_enabled' => true],
        ]);
        $run = $this->syncRun($supplier);
        $product = $this->supplierProduct($supplier, $run, [
            'brand' => 'MAHLE',
            'mpn' => 'OC 123',
            'name' => 'Oil Filter',
        ]);

        $this->promote($supplier, $run);

        $this->assertSame('blocked_rights', $product->fresh()->technical_promotion_status);
        $this->assertDatabaseMissing('catalog_sources', ['supplier_id' => $supplier->id]);
        $this->assertDatabaseCount('catalog_parts', 0);
    }

    public function test_augment_only_mode_waits_for_a_canonical_part_instead_of_creating_one(): void
    {
        $supplier = $this->supplier([
            'allow_derived_data' => true,
            'settings' => [
                'technical_promotion_enabled' => true,
                'technical_promotion_create_parts' => false,
            ],
        ]);
        $run = $this->syncRun($supplier);
        $product = $this->supplierProduct($supplier, $run, [
            'brand' => 'MAHLE',
            'mpn' => 'OC 123',
            'name' => 'Oil Filter',
        ]);

        $this->promote($supplier, $run);

        $product->refresh();
        $this->assertSame('awaiting_canonical_part', $product->technical_promotion_status);
        $this->assertNull($product->catalog_part_id);
        $this->assertDatabaseCount('catalog_parts', 0);
        $this->assertDatabaseHas('catalog_sources', [
            'supplier_id' => $supplier->id,
            'allow_derived' => true,
            'allow_api_redistribution' => false,
        ]);
    }

    public function test_permissioned_feed_can_create_a_part_with_exact_artifact_provenance_and_deferred_cross_reference(): void
    {
        $supplier = $this->supplier([
            'allow_derived_data' => true,
            'allow_ecommerce_data' => true,
            'allow_api_redistribution' => false,
            'settings' => [
                'technical_promotion_enabled' => true,
                'technical_promotion_create_parts' => true,
                'technical_part_confidence' => 97,
            ],
        ]);
        $run = $this->syncRun($supplier);
        $artifact = SupplierFeedArtifact::query()->create([
            'supplier_id' => $supplier->id,
            'supplier_sync_run_id' => $run->id,
            'mode' => 'catalog',
            'source_path' => 'exports/material-20260906.csv',
            'filename' => 'material-20260906.csv',
            'size_bytes' => 12345,
            'checksum_sha256' => str_repeat('a', 64),
            'retrieved_at' => now(),
        ]);
        $product = $this->supplierProduct($supplier, $run, [
            'brand' => 'MAHLE',
            'mpn' => 'OC 123',
            'ean' => '4009026000123',
            'name' => 'Oil Filter',
            'description' => 'Technical description',
            'oe_numbers' => [['number' => '90915YZZD2', 'scheme' => 'OE']],
            'iam_numbers' => [['number' => 'ALT-123', 'scheme' => 'IAM']],
            'cross_references' => [['brand' => 'MANN-FILTER', 'number' => 'W 68/3', 'scheme' => 'MPN']],
        ]);

        $this->promote($supplier, $run);

        $product->refresh();
        $this->assertSame('promoted', $product->technical_promotion_status);
        $this->assertNotNull($product->catalog_part_id);

        $source = CatalogSource::query()->where('supplier_id', $supplier->id)->firstOrFail();
        $this->assertFalse($source->is_active);
        $this->assertTrue($source->allow_ecommerce);
        $this->assertFalse($source->allow_api_redistribution);

        $record = CatalogSourceRecord::query()->where('supplier_product_id', $product->id)->firstOrFail();
        $this->assertSame($run->id, $record->supplier_sync_run_id);
        $this->assertSame($artifact->id, $record->supplier_feed_artifact_id);
        $this->assertSame('published', $record->mapping_status);

        $this->assertDatabaseHas('catalog_part_numbers', [
            'catalog_part_id' => $product->catalog_part_id,
            'scheme' => 'EAN_GTIN',
            'catalog_source_record_id' => $record->id,
        ]);
        $this->assertDatabaseHas('catalog_part_numbers', [
            'catalog_part_id' => $product->catalog_part_id,
            'scheme' => 'OE',
            'catalog_source_record_id' => $record->id,
        ]);
        $this->assertDatabaseHas('catalog_unresolved_part_relations', [
            'source_part_id' => $product->catalog_part_id,
            'relation_type' => 'equivalent',
            'status' => 'pending',
            'catalog_source_record_id' => $record->id,
        ]);

        $assertion = CatalogSourceAssertion::query()
            ->where('catalog_source_record_id', $record->id)
            ->where('field_or_relation', 'cross_references')
            ->firstOrFail();
        $this->assertTrue($assertion->ecommerce_displayable);
        $this->assertFalse($assertion->api_redistributable);
    }

    public function test_deferred_cross_reference_resolves_when_target_part_arrives(): void
    {
        $supplier = $this->supplier([
            'allow_derived_data' => true,
            'settings' => [
                'technical_promotion_enabled' => true,
                'technical_promotion_create_parts' => true,
            ],
        ]);
        $run = $this->syncRun($supplier);
        $product = $this->supplierProduct($supplier, $run, [
            'brand' => 'MAHLE',
            'mpn' => 'OC 123',
            'name' => 'Oil Filter',
            'cross_references' => [['brand' => 'MANN-FILTER', 'number' => 'W 68/3', 'scheme' => 'MPN']],
        ]);
        $this->promote($supplier, $run);

        $pending = CatalogUnresolvedPartRelation::query()->firstOrFail();
        $target = $this->catalogPart('MANN-FILTER', 'W 68/3', 'MANN oil filter');

        $resolved = app(CatalogPartRelationResolver::class)->resolvePending();

        $this->assertSame(1, $resolved);
        $pending->refresh();
        $this->assertSame('resolved', $pending->status);
        $this->assertSame($target->id, $pending->resolved_target_part_id);
        $this->assertDatabaseHas('catalog_part_relations', [
            'source_part_id' => $product->fresh()->catalog_part_id,
            'target_part_id' => $target->id,
            'relation_type' => 'equivalent',
        ]);
    }

    public function test_technical_identity_conflict_does_not_overwrite_existing_commerce_mapping(): void
    {
        $supplier = $this->supplier([
            'allow_derived_data' => true,
            'settings' => [
                'technical_promotion_enabled' => true,
                'technical_promotion_create_parts' => false,
            ],
        ]);
        $commercePart = $this->catalogPart('OTHER', 'A-1', 'Commerce mapping');
        $technicalPart = $this->catalogPart('MAHLE', 'OC 123', 'Technical identity');
        $run = $this->syncRun($supplier);
        $product = $this->supplierProduct($supplier, $run, [
            'brand' => 'MAHLE',
            'mpn' => 'OC 123',
            'name' => 'Oil Filter',
        ], $commercePart->id);

        $this->promote($supplier, $run);

        $product->refresh();
        $this->assertSame('conflict', $product->technical_promotion_status);
        $this->assertSame($commercePart->id, $product->catalog_part_id);
        $this->assertNotSame($technicalPart->id, $product->catalog_part_id);
        $this->assertDatabaseHas('catalog_conflicts', [
            'entity_type' => 'supplier_product',
            'entity_id' => $product->id,
            'field_or_relation' => 'technical_identity_mismatch',
            'status' => 'open',
        ]);
    }

    private function supplier(array $overrides = []): Supplier
    {
        return Supplier::query()->create(array_replace([
            'name' => 'Permissioned Manufacturer',
            'code' => 'PERMISSIONED_MANUFACTURER_'.Str::upper(Str::random(6)),
            'protocol' => SupplierProtocol::Sftp,
            'default_currency' => 'EUR',
            'timezone' => 'Europe/Berlin',
            'priority' => 20,
            'data_rights_class' => CatalogRightsClass::PermissionedRedistributable,
            'allow_internal_data' => true,
            'allow_ecommerce_data' => true,
            'allow_derived_data' => true,
            'allow_api_redistribution' => false,
            'attribution_required' => false,
            'settings' => [],
            'is_active' => true,
        ], $overrides));
    }

    private function syncRun(Supplier $supplier): SupplierSyncRun
    {
        return SupplierSyncRun::query()->create([
            'uuid' => (string) Str::uuid(),
            'supplier_id' => $supplier->id,
            'mode' => 'catalog',
            'status' => SyncStatus::Completed,
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);
    }

    private function supplierProduct(Supplier $supplier, SupplierSyncRun $run, array $technicalPayload, ?int $catalogPartId = null): SupplierProduct
    {
        return SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'external_id' => 'EXT-'.Str::upper(Str::random(8)),
            'manufacturer_part_number' => $technicalPayload['mpn'] ?? null,
            'raw_brand' => $technicalPayload['brand'] ?? null,
            'name' => $technicalPayload['name'] ?? 'Part',
            'raw_payload' => $technicalPayload,
            'technical_payload' => $technicalPayload,
            'last_supplier_sync_run_id' => $run->id,
            'technical_promotion_status' => 'pending',
            'catalog_mapping_status' => $catalogPartId ? 'mapped_manual' : 'unmapped',
            'catalog_part_id' => $catalogPartId,
            'last_seen_at' => now(),
        ]);
    }

    private function catalogPart(string $brandName, string $mpn, string $name): CatalogPart
    {
        $brand = Brand::query()->firstOrCreate(
            ['slug' => Str::slug($brandName)],
            ['name' => $brandName, 'is_active' => true],
        );
        $normalizer = app(IdentifierNormalizer::class);
        $part = CatalogPart::query()->create([
            'public_id' => (string) Str::ulid(),
            'brand_id' => $brand->id,
            'mpn_raw' => $mpn,
            'mpn_normalized' => $normalizer->normalize($mpn),
            'name' => $name,
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
            'confidence' => 100,
        ]);

        return $part;
    }

    private function promote(Supplier $supplier, SupplierSyncRun $run): void
    {
        (new PromoteSupplierTechnicalData($supplier->id, $run->id))->handle(
            app(SupplierTechnicalSourceBridge::class),
            app(CatalogCanonicalizerRegistry::class),
            app(CatalogPartRelationResolver::class),
        );
    }
}
