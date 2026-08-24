<?php

namespace App\Suppliers;

use App\Enums\ProductStatus;
use App\Jobs\EvaluateProductAlerts;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\SupplierOffer;
use App\Models\SupplierProduct;
use App\Suppliers\Data\SupplierRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SupplierCatalogImporter
{
    /** @return array{created: bool, updated: bool} */
    public function import(Supplier $supplier, SupplierRecord $record, string $mode): array
    {
        $result = DB::transaction(function () use ($supplier, $record, $mode): array {
            $sourceHash = hash('sha256', json_encode($record->raw, JSON_THROW_ON_ERROR));
            $supplierProduct = SupplierProduct::query()->firstOrNew([
                'supplier_id' => $supplier->id,
                'external_id' => $record->externalId,
            ]);
            $created = ! $supplierProduct->exists;
            $changed = $created || $supplierProduct->source_hash !== $sourceHash;

            $variant = $this->findCanonicalVariant($record);
            if (! $variant && ($supplier->settings['auto_create_products'] ?? false)) {
                $variant = $this->createCanonicalProduct($supplier, $record);
            }

            $supplierProduct->fill([
                'product_id' => $variant?->product_id,
                'variant_id' => $variant?->id,
                'supplier_sku' => $record->sku,
                'ean' => $record->ean,
                'name' => $record->name,
                'source_url' => $record->sourceUrl,
                'source_hash' => $sourceHash,
                'raw_payload' => $record->raw,
                'mapping_status' => $variant ? 'mapped' : 'unmapped',
                'last_seen_at' => now(),
                'discontinued_at' => null,
            ])->save();

            $offer = SupplierOffer::query()->firstOrNew(['supplier_product_id' => $supplierProduct->id]);
            $old = $offer->only(['cost_price', 'recommended_retail_price', 'stock_quantity', 'stock_status']);
            $offer->fill([
                'cost_price' => $record->costPrice,
                'recommended_retail_price' => $record->recommendedRetailPrice,
                'currency' => $record->currency,
                'stock_quantity' => $record->stockQuantity,
                'stock_status' => $record->stockStatus,
                'lead_time_days' => $record->leadTimeDays,
                'price_synced_at' => in_array($mode, ['catalog', 'prices'], true) ? now() : $offer->price_synced_at,
                'stock_synced_at' => in_array($mode, ['catalog', 'stock'], true) ? now() : $offer->stock_synced_at,
                'stale_after' => now()->addMinutes($supplier->settings['stale_after_minutes'] ?? 60),
            ])->save();

            $new = $offer->only(['cost_price', 'recommended_retail_price', 'stock_quantity', 'stock_status']);
            $offerChanged = $offer->wasRecentlyCreated || $old !== $new;
            if (! $offer->wasRecentlyCreated && $offerChanged) {
                DB::table('supplier_offer_history')->insert($new + [
                    'supplier_offer_id' => $offer->id,
                    'recorded_at' => now(),
                ]);
            }

            return [
                'created' => $created,
                'updated' => ! $created && $changed,
                'product_id' => $variant?->product_id,
                'offer_changed' => $offerChanged,
            ];
        }, attempts: 3);

        if ($result['product_id'] && $result['offer_changed']) {
            EvaluateProductAlerts::dispatch($result['product_id'])->afterCommit();
        }

        return ['created' => $result['created'], 'updated' => $result['updated']];
    }

    private function findCanonicalVariant(SupplierRecord $record): ?ProductVariant
    {
        if (! $record->ean && ! $record->manufacturerPartNumber) {
            return null;
        }

        return ProductVariant::query()
            ->when($record->ean, fn ($query) => $query->where('barcode', $record->ean))
            ->when(! $record->ean && $record->manufacturerPartNumber, fn ($query) => $query->where('manufacturer_part_number', $record->manufacturerPartNumber))
            ->first();
    }

    private function createCanonicalProduct(Supplier $supplier, SupplierRecord $record): ProductVariant
    {
        $brand = filled($record->brand)
            ? Brand::query()->firstOrCreate(['slug' => Str::slug($record->brand)], ['name' => $record->brand])
            : null;

        $product = Product::query()->create([
            'brand_id' => $brand?->id,
            'name' => $record->name,
            'slug' => Str::slug($record->name).'-'.Str::lower(Str::random(6)),
            'sku' => null,
            'manufacturer_part_number' => $record->manufacturerPartNumber,
            'status' => ProductStatus::Review,
            'description' => $record->description,
            'metadata' => ['created_from_supplier' => $supplier->code],
        ]);

        return $product->variants()->create([
            'sku' => $supplier->code.'-'.($record->sku ?: $record->externalId),
            'barcode' => $record->ean,
            'manufacturer_part_number' => $record->manufacturerPartNumber,
            'retail_price' => $record->recommendedRetailPrice,
            'currency' => $record->currency,
        ]);
    }
}
