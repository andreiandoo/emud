<?php

namespace App\Suppliers;

use App\Commerce\CurrencyConverter;
use App\Enums\ProductStatus;
use App\Jobs\EvaluateProductAlerts;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\SupplierOffer;
use App\Models\SupplierProduct;
use App\Models\SupplierSyncRun;
use App\Models\SupplierWarehouse;
use App\Suppliers\Data\SupplierRecord;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class SupplierCatalogImporter
{
    public function __construct(private readonly CurrencyConverter $converter) {}

    /** @return array{created: bool, updated: bool} */
    public function import(Supplier $supplier, SupplierRecord $record, string $mode, ?SupplierSyncRun $run = null): array
    {
        $result = DB::transaction(function () use ($supplier, $record, $mode, $run): array {
            $sourceHash = hash('sha256', json_encode($record->raw, JSON_THROW_ON_ERROR));
            $supplierProduct = SupplierProduct::query()->firstOrNew(['supplier_id' => $supplier->id, 'external_id' => $record->externalId]);
            $created = ! $supplierProduct->exists;
            $changed = $created || $supplierProduct->source_hash !== $sourceHash;
            $variant = $this->findCanonicalVariant($record);

            if (! $variant && ($supplier->settings['auto_create_products'] ?? false)) {
                $variant = $this->createCanonicalProduct($supplier, $record);
            }

            $productPayload = [
                'product_id' => $variant?->product_id,
                'variant_id' => $variant?->id,
                'supplier_sku' => $record->sku,
                'ean' => $record->ean,
                'manufacturer_part_number' => $record->manufacturerPartNumber,
                'raw_brand' => $record->brand,
                'name' => $record->name,
                'source_url' => $record->sourceUrl,
                'source_hash' => $sourceHash,
                'raw_payload' => $record->raw,
                'mapping_status' => $variant ? 'mapped' : 'unmapped',
                'catalog_mapping_status' => $supplierProduct->catalog_mapping_status ?: 'unmapped',
                'last_seen_at' => now(),
                'discontinued_at' => null,
            ];

            if ($mode === 'catalog') {
                $productPayload += [
                    'technical_payload' => $this->technicalPayload($record),
                    'last_supplier_sync_run_id' => $run?->id,
                    'technical_promotion_status' => $this->technicalPromotionStatus($supplier, $record),
                    'technical_promotion_error' => null,
                    'technical_promoted_at' => null,
                ];
            }

            $supplierProduct->fill($productPayload)->save();

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
                'is_active' => true,
                'source_type' => $supplier->protocol->value,
                ...$this->economics($supplier, $record),
                ...$this->baseCurrencyCost($record),
            ])->save();

            $new = $offer->only(['cost_price', 'recommended_retail_price', 'stock_quantity', 'stock_status']);
            $offerChanged = $offer->wasRecentlyCreated || $old !== $new;
            if (! $offer->wasRecentlyCreated && $offerChanged) {
                DB::table('supplier_offer_history')->insert($new + ['supplier_offer_id' => $offer->id, 'recorded_at' => now()]);
            }

            // Stock history is written only on an actual change, so a feed that runs
            // every fifteen minutes does not add four identical rows an hour per SKU.
            if ($offer->wasRecentlyCreated || $old['stock_quantity'] !== $new['stock_quantity'] || $old['stock_status'] !== $new['stock_status']) {
                DB::table('supplier_stock_history')->insert([
                    'supplier_offer_id' => $offer->id,
                    'stock_quantity' => $record->stockQuantity,
                    'stock_status' => $record->stockStatus,
                    'recorded_at' => now(),
                ]);
            }

            return ['created' => $created, 'updated' => ! $created && $changed, 'product_id' => $variant?->product_id, 'offer_changed' => $offerChanged];
        }, attempts: 3);

        if ($result['product_id'] && $result['offer_changed']) {
            EvaluateProductAlerts::dispatch($result['product_id'])->afterCommit();
        }

        return ['created' => $result['created'], 'updated' => $result['updated']];
    }

    /**
     * Commercial and logistics fields, plus the warehouse the offer sits in.
     *
     * Supplier-level defaults fill in only where the feed said nothing, so a
     * per-article dropship fee always beats the account-wide one.
     *
     * @return array<string, mixed>
     */
    private function economics(Supplier $supplier, SupplierRecord $record): array
    {
        return array_filter([
            'supplier_warehouse_id' => $this->warehouseId($supplier, $record),
            'warehouse_code' => $record->warehouseCode,
            'cost_gross' => $record->costGross,
            'map_price' => $record->mapPrice,
            'msrp' => $record->msrp,
            'dropship_fee' => $record->dropshipFee ?? $supplier->dropship_fee,
            'handling_fee' => $record->handlingFee ?? $supplier->packaging_fee,
            'shipping_cost_estimate' => $record->shippingCostEstimate,
            'pack_quantity' => $record->packQuantity,
            'minimum_order_quantity' => $record->minimumOrderQuantity,
            'dispatch_days_min' => $record->dispatchDaysMin ?? $supplier->default_dispatch_days_min,
            'dispatch_days_max' => $record->dispatchDaysMax ?? $supplier->default_dispatch_days_max,
            'shipping_class' => $record->shippingClass,
            'weight_kg' => $record->weightKg,
            'packed_weight_kg' => $record->packedWeightKg,
            'length_cm' => $record->lengthCm,
            'width_cm' => $record->widthCm,
            'height_cm' => $record->heightCm,
            'oversize_flag' => $record->oversize,
            'hazmat_flag' => $record->hazmat,
            'is_dropship_eligible' => $record->dropshipEligible,
            'source_seller_ref' => $record->sellerRef,
            'source_seller_name' => $record->sellerName,
            'source_updated_at' => $this->parseTimestamp($record->sourceUpdatedAt),
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * The supplier's own price stays untouched; this only adds what it is worth in
     * the base currency, with the rate and the day it came from.
     *
     * @return array<string, mixed>
     */
    private function baseCurrencyCost(SupplierRecord $record): array
    {
        if ($record->costPrice === null) {
            return [];
        }

        $converted = $this->converter->convert($record->costPrice, $record->currency);

        // No rate is not an error worth failing the row over: the raw cost is still
        // recorded and the landed cost simply reports itself as incomplete.
        return $converted ? [
            'base_currency' => $converted['currency'],
            'base_cost_net' => $converted['amount'],
            'fx_rate' => $converted['rate'],
            'fx_rate_at' => $converted['rate_date'],
        ] : [];
    }

    private function warehouseId(Supplier $supplier, SupplierRecord $record): ?int
    {
        if (blank($record->warehouseCode)) {
            return null;
        }

        return SupplierWarehouse::query()->firstOrCreate(
            ['supplier_id' => $supplier->id, 'code' => $record->warehouseCode],
        )->id;
    }

    private function parseTimestamp(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateTimeString();
        } catch (Throwable) {
            return null;
        }
    }

    private function technicalPromotionStatus(Supplier $supplier, SupplierRecord $record): string
    {
        if (! $supplier->allow_derived_data) {
            return 'blocked_rights';
        }

        if (! (bool) ($supplier->settings['technical_promotion_enabled'] ?? false)) {
            return 'disabled';
        }

        if (! $record->brand || ! $record->manufacturerPartNumber) {
            return 'insufficient_identity';
        }

        return 'pending';
    }

    /** @return array<string, mixed> */
    private function technicalPayload(SupplierRecord $record): array
    {
        return array_filter([
            'brand' => $record->brand,
            'mpn' => $record->manufacturerPartNumber,
            'ean' => $record->ean,
            'name' => $record->name,
            'description' => $record->description,
            'category' => $record->categoryExternalId,
            'oe_numbers' => $record->oeNumbers,
            'iam_numbers' => $record->iamNumbers,
            'cross_references' => $record->crossReferences,
            'supersessions' => $record->supersessions,
            'attributes' => $record->attributes,
            'fitments' => $record->fitments,
        ], static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);
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
        $brand = filled($record->brand) ? Brand::query()->firstOrCreate(['slug' => Str::slug($record->brand)], ['name' => $record->brand]) : null;
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
