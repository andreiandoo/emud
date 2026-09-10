<?php

namespace App\Suppliers;

use App\Catalog\CollectionMatcher;
use App\Commerce\CurrencyConverter;
use App\Enums\ProductStatus;
use App\Jobs\EvaluateProductAlerts;
use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\SupplierOffer;
use App\Models\SupplierProduct;
use App\Models\SupplierSyncRun;
use App\Models\SupplierWarehouse;
use App\Models\VehicleConfiguration;
use App\Suppliers\Data\SupplierRecord;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class SupplierCatalogImporter
{
    public function __construct(
        private readonly CurrencyConverter $converter,
        private readonly CollectionMatcher $collections,
        private readonly SupplierIdentifierSync $identifiers,
    ) {}

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

            // Only when the row changed: a stock feed every fifteen minutes carries the
            // same identity each time, and rewriting it would be pure write load.
            if ($changed) {
                $this->identifiers->sync($supplier, $supplierProduct, $record);
            }

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
            'short_description' => $this->shortDescription($record),
            'description' => $record->description,
            // Nothing to fit means it applies to everything, which is what the storefront reads
            // to decide whether to show a product to a customer who has picked a vehicle.
            'is_universal' => $record->fitments === [],
            'warranty_months' => $this->warrantyMonths($record),
            'weight_kg' => $record->packedWeightKg ?? $record->weightKg,
            'dimensions_cm' => $this->dimensions($record),
            'metadata' => ['created_from_supplier' => $supplier->code],
        ]);

        $this->attachCategory($supplier, $product, $record);
        $this->attachAttributes($product, $record);
        $this->attachFitments($product, $record);
        // Right after the fitments, because those are the only thing collection matching reads.
        // Doing it here rather than on a queue keeps a newly imported part out of a collection
        // page for zero seconds instead of however long the queue is behind.
        $this->collections->syncForProduct($product->load('fitments'));

        $retail = $this->retailInBaseCurrency($record);

        return $product->variants()->create([
            'sku' => $supplier->code.'-'.($record->sku ?: $record->externalId),
            'barcode' => $record->ean,
            'manufacturer_part_number' => $record->manufacturerPartNumber,
            'retail_price' => $retail['amount'],
            'currency' => $retail['currency'],
            'weight_kg' => $record->weightKg,
        ]);
    }

    /**
     * The shop quotes one currency. A supplier's own is kept on the offer, alongside the
     * converted cost and the rate it used, but what reaches the shelf is converted.
     *
     * Without a rate the supplier's currency is kept rather than relabelled: printing a EUR
     * number as RON is not a rounding error, it is a wrong price on a product page.
     *
     * @return array{amount: ?float, currency: string}
     */
    private function retailInBaseCurrency(SupplierRecord $record): array
    {
        $base = $this->converter->baseCurrency();

        if ($record->recommendedRetailPrice === null) {
            return ['amount' => null, 'currency' => $base];
        }

        $converted = $this->converter->convert($record->recommendedRetailPrice, $record->currency);

        return $converted
            ? ['amount' => $converted['amount'], 'currency' => $converted['currency']]
            : ['amount' => $record->recommendedRetailPrice, 'currency' => $record->currency];
    }

    /**
     * Feeds rarely carry a separate teaser, so the first sentence of the description stands in.
     * A product listing with no summary at all reads as broken; a truncated one does not.
     */
    private function shortDescription(SupplierRecord $record): ?string
    {
        if (blank($record->description)) {
            return null;
        }

        $firstSentence = preg_split('/(?<=[.!?])\s+/', trim($record->description), 2)[0] ?? '';

        return Str::limit($firstSentence !== '' ? $firstSentence : trim($record->description), 250);
    }

    /** @return array<string, float>|null */
    private function dimensions(SupplierRecord $record): ?array
    {
        $dimensions = array_filter([
            'length' => $record->lengthCm,
            'width' => $record->widthCm,
            'height' => $record->heightCm,
        ], static fn (?float $value): bool => $value !== null);

        return $dimensions === [] ? null : $dimensions;
    }

    /**
     * Warranty is not a first-class feed field anywhere, but suppliers do put it in their spec
     * blob, so it is read from there rather than left empty on every product.
     */
    private function warrantyMonths(SupplierRecord $record): ?int
    {
        foreach ($record->attributes as $key => $value) {
            if (! preg_match('/garan|warrant/i', (string) $key)) {
                continue;
            }

            if (preg_match('/(\d+)/', (string) $value, $matches)) {
                $months = (int) $matches[1];

                // "2 ani" and "24 luni" are the same warranty spelled two ways.
                return preg_match('/an|year/i', (string) $value) && $months <= 10 ? $months * 12 : $months;
            }
        }

        return null;
    }

    /**
     * The supplier's own category label is matched against the tree by name or full path, the
     * same way the canonicalizer does it, and through the same mapping rules an operator would
     * use to correct it. An unmatched label leaves the product uncategorised rather than
     * inventing a branch nobody asked for.
     */
    private function attachCategory(Supplier $supplier, Product $product, SupplierRecord $record): void
    {
        if (blank($record->categoryExternalId)) {
            return;
        }

        $label = trim($record->categoryExternalId);
        $mapped = $supplier->settings['category_map'][$label] ?? null;

        $category = is_numeric($mapped)
            ? Category::query()->find((int) $mapped)
            : Category::query()->where('full_path', 'ilike', $label)->orWhere('name', 'ilike', $label)->first();

        if ($category) {
            $product->categories()->syncWithoutDetaching([$category->id => ['is_primary' => true]]);
        }
    }

    /**
     * Only attributes the catalogue already defines are written, matched on code or name. A
     * supplier inventing attribute definitions would turn the filter sidebar into a junk drawer
     * within one import.
     */
    private function attachAttributes(Product $product, SupplierRecord $record): void
    {
        foreach ($record->attributes as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $code = (string) $key;
            $attribute = Attribute::query()
                ->where('code', $code)
                ->orWhere('name', 'ilike', $code)
                ->first();

            if (! $attribute) {
                continue;
            }

            $product->attributeValues()->create(
                ['attribute_id' => $attribute->id] + $this->attributeValue($attribute, $value),
            );
        }
    }

    /** @return array<string, mixed> */
    private function attributeValue(Attribute $attribute, mixed $value): array
    {
        if (in_array($attribute->type, ['select', 'color'], true)) {
            $option = $attribute->options()
                ->where(fn ($query) => $query->where('value', (string) $value)->orWhere('label', 'ilike', (string) $value))
                ->first();

            // A value the attribute has no option for is still worth keeping as text: losing it
            // would hide a real specification just because the option list is incomplete.
            return $option ? ['option_id' => $option->id] : ['value_text' => (string) $value];
        }

        return match (true) {
            $attribute->type === 'number' && is_numeric($value) => ['value_number' => $value],
            $attribute->type === 'boolean' => ['value_boolean' => (bool) $value],
            is_array($value) => ['value_json' => $value],
            default => ['value_text' => is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE)],
        };
    }

    /**
     * Feed fitments name a vehicle configuration; product fitments are recorded at generation
     * level, which is the granularity the storefront filters on. Several configurations of the
     * same generation therefore collapse into one row rather than repeating it per engine.
     */
    private function attachFitments(Product $product, SupplierRecord $record): void
    {
        $configurationIds = [];

        foreach ($record->fitments as $fitment) {
            $id = is_array($fitment) ? ($fitment['configuration_id'] ?? null) : null;
            if (is_numeric($id)) {
                $configurationIds[] = (int) $id;
            }
        }

        if ($configurationIds === []) {
            return;
        }

        $generations = VehicleConfiguration::query()
            ->with('generation.model')
            ->whereIn('id', array_unique($configurationIds))
            ->get()
            ->groupBy('generation_id');

        foreach ($generations as $configurations) {
            $generation = $configurations->first()->generation;
            if (! $generation?->model) {
                continue;
            }

            $years = $configurations->pluck('year')->filter();

            $product->fitments()->create([
                'make_id' => $generation->model->make_id,
                'model_id' => $generation->model_id,
                'generation_id' => $generation->id,
                'year_from' => $years->min() ?: $generation->year_from,
                'year_to' => $years->max() ?: $generation->year_to,
                'source' => 'supplier',
            ]);
        }
    }
}
