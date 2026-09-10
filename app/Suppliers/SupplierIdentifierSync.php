<?php

namespace App\Suppliers;

use App\Catalog\Normalization\GtinValidator;
use App\Catalog\Normalization\IdentifierNormalizer;
use App\Enums\SupplierIdentifierType;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Suppliers\Data\SupplierRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Writes every identifier a feed row carries into supplier_product_identifiers.
 *
 * Runs inside the importer's per-row transaction and only when the row's source
 * hash changed, so a stock feed every fifteen minutes does not rewrite identity
 * that has not moved.
 */
class SupplierIdentifierSync
{
    public function __construct(
        private readonly IdentifierNormalizer $normalizer,
        private readonly GtinValidator $gtins,
    ) {}

    /** @return array{written: int, invalid_gtins: list<string>} */
    public function sync(Supplier $supplier, SupplierProduct $product, SupplierRecord $record): array
    {
        $invalidGtins = [];
        $rows = $this->rowsFor($supplier, $record, $invalidGtins);

        // Replace rather than merge: an OE number the supplier dropped from its feed
        // must stop matching, not linger as a ghost identity.
        DB::table('supplier_product_identifiers')->where('supplier_product_id', $product->id)->delete();

        if ($rows !== []) {
            $now = now();
            DB::table('supplier_product_identifiers')->insert(array_map(
                static fn (array $row): array => [...$row, 'supplier_product_id' => $product->id, 'created_at' => $now],
                $rows,
            ));
        }

        return ['written' => count($rows), 'invalid_gtins' => $invalidGtins];
    }

    /**
     * @param  list<string>  $invalidGtins  Filled with barcodes rejected as placeholders or typos.
     * @return list<array{type: string, value: string, compact_value: string, brand: ?string}>
     */
    public function rowsFor(Supplier $supplier, SupplierRecord $record, array &$invalidGtins = []): array
    {
        $rows = [];

        $add = function (SupplierIdentifierType $type, ?string $value, ?string $compact = null, ?string $brand = null) use (&$rows): void {
            if (blank($value)) {
                return;
            }

            $compact = Str::limit($compact ?? $this->normalizer->compact((string) $value), 255, '');

            if ($compact === '') {
                return;
            }

            // Keyed so the same number listed twice, or as both OE and cross
            // reference spelled differently, is one row per type.
            $rows["{$type->value}:{$compact}"] = [
                'type' => $type->value,
                'value' => trim((string) $value),
                'compact_value' => $compact,
                'brand' => blank($brand) ? null : trim((string) $brand),
            ];
        };

        foreach ([$record->ean, $record->upc] as $barcode) {
            if (blank($barcode)) {
                continue;
            }

            $canonical = $this->gtins->canonical($barcode);

            if ($canonical === null) {
                $invalidGtins[] = (string) $barcode;

                continue;
            }

            // Stored in the fourteen-digit form so EAN-13 and UPC-A of the same box
            // are one identity rather than two.
            $add(SupplierIdentifierType::Gtin, $barcode, $canonical);
        }

        $add(SupplierIdentifierType::Mpn, $record->manufacturerPartNumber, $this->mpnCompact($supplier, $record->manufacturerPartNumber), $record->brand);
        $add(SupplierIdentifierType::TecDocArticle, $record->tecdocArticleId, null, $record->brand);
        $add(SupplierIdentifierType::SupplierSku, $record->sku);

        foreach ($record->oeNumbers as $reference) {
            [$number, $brand] = $this->referenceParts($reference);
            $add(SupplierIdentifierType::Oe, $number, null, $brand);
        }

        foreach ($record->iamNumbers as $reference) {
            [$number, $brand] = $this->referenceParts($reference);
            $add(SupplierIdentifierType::Iam, $number, null, $brand);
        }

        foreach ($record->crossReferences as $reference) {
            [$number, $brand] = $this->referenceParts($reference);
            $add(SupplierIdentifierType::CrossReference, $number, null, $brand);
        }

        foreach ($record->supersessions as $reference) {
            [$number, $brand] = $this->referenceParts($reference);
            $add(SupplierIdentifierType::Supersession, $number, null, $brand ?? $record->brand);
        }

        return array_values($rows);
    }

    /**
     * Some suppliers prefix the manufacturer's number with their own code ("BOS-0 986
     * 494 123"). Prefixes are configured per supplier rather than guessed, because
     * stripping a leading token that turns out to be part of the number would match
     * the wrong part with full confidence.
     */
    public function mpnCompact(Supplier $supplier, ?string $mpn): ?string
    {
        if (blank($mpn)) {
            return null;
        }

        return $this->normalizer->compact($this->stripMpnPrefix($supplier, (string) $mpn));
    }

    public function stripMpnPrefix(Supplier $supplier, string $mpn): string
    {
        $value = trim($mpn);

        foreach ((array) ($supplier->settings['mpn_strip_prefixes'] ?? []) as $prefix) {
            $prefix = (string) $prefix;

            if ($prefix !== '' && str_starts_with(Str::upper($value), Str::upper($prefix))) {
                return substr($value, strlen($prefix));
            }
        }

        return $value;
    }

    /**
     * Feeds list references either as bare strings or as small objects naming the
     * manufacturer. Both are accepted; the key for the number varies by supplier.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function referenceParts(mixed $reference): array
    {
        if (is_scalar($reference)) {
            return [(string) $reference, null];
        }

        if (! is_array($reference)) {
            return [null, null];
        }

        $number = $reference['number'] ?? $reference['mpn'] ?? $reference['value'] ?? $reference['ref'] ?? null;
        $brand = $reference['brand'] ?? $reference['manufacturer'] ?? $reference['make'] ?? null;

        return [is_scalar($number) ? (string) $number : null, is_scalar($brand) ? (string) $brand : null];
    }
}
