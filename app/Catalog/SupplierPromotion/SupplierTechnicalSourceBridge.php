<?php

namespace App\Catalog\SupplierPromotion;

use App\Catalog\Canonicalization\Parts\ManufacturerPartCanonicalizer;
use App\Catalog\Normalization\IdentifierNormalizer;
use App\Models\Brand;
use App\Models\CatalogImportRun;
use App\Models\CatalogPart;
use App\Models\CatalogSource;
use App\Models\CatalogSourceRecord;
use App\Models\CatalogSourceRelease;
use App\Models\Supplier;
use App\Models\SupplierFeedArtifact;
use App\Models\SupplierProduct;
use App\Models\SupplierSyncRun;
use Illuminate\Support\Str;
use RuntimeException;

class SupplierTechnicalSourceBridge
{
    public function __construct(private readonly IdentifierNormalizer $normalizer) {}

    public function sourceFor(Supplier $supplier): CatalogSource
    {
        $code = 'SUPPLIER_'.strtoupper($supplier->code);
        $source = CatalogSource::query()
            ->where('supplier_id', $supplier->id)
            ->orWhere('code', $code)
            ->first() ?? new CatalogSource;

        if ($source->exists && $source->supplier_id && $source->supplier_id !== $supplier->id) {
            throw new RuntimeException("Catalog source {$code} is already linked to another supplier.");
        }

        if (! $source->exists) {
            $source->public_id = (string) Str::ulid();
        }

        $source->fill([
            'supplier_id' => $supplier->id,
            'name' => $supplier->name.' technical feed',
            'code' => $code,
            'source_type' => 'permissioned_supplier',
            'protocol' => 'internal',
            'connector_class' => null,
            'canonicalizer_class' => ManufacturerPartCanonicalizer::class,
            'base_url' => null,
            'catalog_endpoint' => null,
            'rights_class' => $supplier->data_rights_class->value,
            'allow_internal' => (bool) $supplier->allow_internal_data,
            'allow_ecommerce' => (bool) $supplier->allow_ecommerce_data,
            'allow_derived' => (bool) $supplier->allow_derived_data,
            'allow_api_redistribution' => (bool) $supplier->allow_api_redistribution,
            'allow_bulk_export' => (bool) ($supplier->settings['allow_bulk_export'] ?? false),
            'allow_media_redistribution' => (bool) ($supplier->settings['allow_media_redistribution'] ?? false),
            'attribution_required' => (bool) $supplier->attribution_required,
            'license_name' => $supplier->license_name,
            'license_url' => $supplier->license_url,
            'legal_notes' => $supplier->legal_notes,
            'territories' => $supplier->settings['territories'] ?? null,
            'capabilities' => [
                'supplier_promotion' => true,
                'technical_identifiers' => true,
                'fitments' => true,
                'cross_references' => true,
            ],
            'field_mapping' => [
                'brand' => 'brand',
                'mpn' => 'mpn',
                'ean' => 'ean',
                'name' => 'name',
                'description' => 'description',
                'category' => 'category',
                'oe_numbers' => 'oe_numbers',
                'iam_numbers' => 'iam_numbers',
                'cross_references' => 'cross_references',
                'supersessions' => 'supersessions',
                'attributes' => 'attributes',
                'fitments' => 'fitments',
            ],
            'settings' => array_replace($source->settings ?? [], [
                'managed_supplier_promotion' => true,
                'auto_canonicalize' => false,
                'part_confidence' => (float) ($supplier->settings['technical_part_confidence'] ?? 95),
                'attribute_confidence' => (float) ($supplier->settings['technical_attribute_confidence'] ?? 95),
                'fitment_confidence' => (float) ($supplier->settings['technical_fitment_confidence'] ?? 98),
                'relation_confidence' => (float) ($supplier->settings['technical_relation_confidence'] ?? 95),
            ]),
            // This source is populated by the supplier bridge, never by the generic catalog scheduler.
            'is_active' => false,
        ]);
        $source->save();

        return $source;
    }

    public function releaseFor(CatalogSource $source, SupplierSyncRun $syncRun, ?SupplierFeedArtifact $artifact): CatalogSourceRelease
    {
        $releaseKey = $artifact
            ? "supplier-artifact:{$artifact->id}:{$artifact->checksum_sha256}"
            : "supplier-sync-run:{$syncRun->uuid}";

        return CatalogSourceRelease::query()->updateOrCreate(
            ['catalog_source_id' => $source->id, 'release_key' => $releaseKey],
            [
                'published_at' => $artifact?->source_modified_at ?? $syncRun->finished_at,
                'retrieved_at' => $artifact?->retrieved_at ?? $syncRun->finished_at ?? now(),
                'checksum_sha256' => $artifact?->checksum_sha256,
                'raw_object_path' => $artifact?->source_path,
                'metadata' => [
                    'supplier_id' => $syncRun->supplier_id,
                    'supplier_sync_run_id' => $syncRun->id,
                    'supplier_feed_artifact_id' => $artifact?->id,
                    'filename' => $artifact?->filename,
                    'size_bytes' => $artifact?->size_bytes,
                ],
            ],
        );
    }

    public function stage(
        CatalogSource $source,
        CatalogImportRun $run,
        SupplierProduct $product,
        SupplierSyncRun $syncRun,
        ?SupplierFeedArtifact $artifact,
        CatalogSourceRelease $release,
    ): CatalogSourceRecord {
        $payload = $product->technical_payload ?? [];
        $checksum = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        $record = CatalogSourceRecord::query()->firstOrNew([
            'catalog_source_id' => $source->id,
            'record_type' => 'manufacturer_part',
            'external_id' => $product->external_id,
        ]);

        $payloadChanged = ! $record->exists || $record->checksum_sha256 !== $checksum;
        $record->fill([
            'catalog_source_release_id' => $release->id,
            'catalog_import_run_id' => $run->id,
            'supplier_product_id' => $product->id,
            'supplier_sync_run_id' => $syncRun->id,
            'supplier_feed_artifact_id' => $artifact?->id,
            'checksum_sha256' => $checksum,
            'raw_payload' => $payload,
            'normalized_payload' => $payload,
            'mapping_status' => $payloadChanged ? 'unprocessed' : $record->mapping_status,
            'mapping_notes' => $payloadChanged ? null : $record->mapping_notes,
            'deleted_at_source' => false,
            'source_updated_at' => $artifact?->source_modified_at,
            'last_seen_at' => now(),
        ]);
        $record->save();

        return $record;
    }

    public function existingCanonicalIdentity(SupplierProduct $product): ?CatalogPart
    {
        $payload = $product->technical_payload ?? [];
        $brandName = trim((string) ($payload['brand'] ?? ''));
        $mpn = trim((string) ($payload['mpn'] ?? ''));
        if ($brandName === '' || $mpn === '') {
            return null;
        }

        $brand = Brand::query()->where('slug', Str::slug($brandName))->first();
        if (! $brand) {
            return null;
        }

        return CatalogPart::query()
            ->where('brand_id', $brand->id)
            ->where('mpn_normalized', $this->normalizer->normalize($mpn))
            ->first();
    }
}
