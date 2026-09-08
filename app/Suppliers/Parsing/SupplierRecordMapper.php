<?php

namespace App\Suppliers\Parsing;

use App\Models\Supplier;
use App\Suppliers\Data\SupplierRecord;
use Illuminate\Support\Arr;

class SupplierRecordMapper
{
    /** @param array<string, mixed> $row */
    public function map(Supplier $supplier, array $row, ?string $sourceUrl = null): ?SupplierRecord
    {
        $mapping = $supplier->field_mapping ?? [];
        $settings = $supplier->settings ?? [];
        $externalId = $this->nullableString($this->value($row, $mapping, 'external_id'));
        $name = $this->nullableString($this->value($row, $mapping, 'name'));

        if (! $externalId || ! $name) {
            return null;
        }

        return new SupplierRecord(
            externalId: $externalId,
            name: $name,
            sku: $this->nullableString($this->value($row, $mapping, 'sku')),
            ean: $this->nullableString($this->value($row, $mapping, 'ean')),
            manufacturerPartNumber: $this->nullableString($this->value($row, $mapping, 'manufacturer_part_number')),
            description: $this->nullableString($this->value($row, $mapping, 'description')),
            brand: $this->nullableString($this->value($row, $mapping, 'brand')),
            categoryExternalId: $this->nullableString($this->value($row, $mapping, 'category_external_id')),
            costPrice: $this->nullableFloat($this->value($row, $mapping, 'cost_price'), $settings),
            recommendedRetailPrice: $this->nullableFloat($this->value($row, $mapping, 'recommended_retail_price'), $settings),
            currency: (string) ($this->value($row, $mapping, 'currency') ?: $supplier->default_currency),
            stockQuantity: $this->nullableInt($this->value($row, $mapping, 'stock_quantity'), $settings),
            stockStatus: $this->stockStatus($this->value($row, $mapping, 'stock_status'), $settings),
            leadTimeDays: $this->nullableInt($this->value($row, $mapping, 'lead_time_days'), $settings),
            sourceUrl: $this->nullableString($this->value($row, $mapping, 'source_url')) ?: $sourceUrl,
            images: $this->arrayValue($this->value($row, $mapping, 'images'), $settings['images_delimiter'] ?? null),
            attributes: $this->associativeArrayValue($this->value($row, $mapping, 'attributes')),
            fitments: $this->arrayValue($this->value($row, $mapping, 'fitments'), $settings['fitments_delimiter'] ?? null),
            oeNumbers: $this->arrayValue($this->value($row, $mapping, 'oe_numbers'), $settings['oe_numbers_delimiter'] ?? null),
            iamNumbers: $this->arrayValue($this->value($row, $mapping, 'iam_numbers'), $settings['iam_numbers_delimiter'] ?? null),
            crossReferences: $this->arrayValue($this->value($row, $mapping, 'cross_references'), $settings['cross_references_delimiter'] ?? null),
            supersessions: $this->arrayValue($this->value($row, $mapping, 'supersessions'), $settings['supersessions_delimiter'] ?? null),
            raw: $row,
            costGross: $this->nullableFloat($this->value($row, $mapping, 'cost_gross'), $settings),
            mapPrice: $this->nullableFloat($this->value($row, $mapping, 'map_price'), $settings),
            msrp: $this->nullableFloat($this->value($row, $mapping, 'msrp'), $settings),
            dropshipFee: $this->nullableFloat($this->value($row, $mapping, 'dropship_fee'), $settings),
            handlingFee: $this->nullableFloat($this->value($row, $mapping, 'handling_fee'), $settings),
            shippingCostEstimate: $this->nullableFloat($this->value($row, $mapping, 'shipping_cost_estimate'), $settings),
            packQuantity: $this->nullableInt($this->value($row, $mapping, 'pack_quantity'), $settings),
            minimumOrderQuantity: $this->nullableInt($this->value($row, $mapping, 'minimum_order_quantity'), $settings),
            dispatchDaysMin: $this->nullableInt($this->value($row, $mapping, 'dispatch_days_min'), $settings),
            dispatchDaysMax: $this->nullableInt($this->value($row, $mapping, 'dispatch_days_max'), $settings),
            shippingClass: $this->shippingClass($this->value($row, $mapping, 'shipping_class'), $settings),
            weightKg: $this->nullableFloat($this->value($row, $mapping, 'weight_kg'), $settings),
            packedWeightKg: $this->nullableFloat($this->value($row, $mapping, 'packed_weight_kg'), $settings),
            lengthCm: $this->nullableFloat($this->value($row, $mapping, 'length_cm'), $settings),
            widthCm: $this->nullableFloat($this->value($row, $mapping, 'width_cm'), $settings),
            heightCm: $this->nullableFloat($this->value($row, $mapping, 'height_cm'), $settings),
            oversize: $this->nullableBool($this->value($row, $mapping, 'oversize'), $settings),
            hazmat: $this->nullableBool($this->value($row, $mapping, 'hazmat'), $settings),
            dropshipEligible: $this->nullableBool($this->value($row, $mapping, 'dropship_eligible'), $settings),
            warehouseCode: $this->nullableString($this->value($row, $mapping, 'warehouse_code')),
            sellerRef: $this->nullableString($this->value($row, $mapping, 'seller_ref')),
            sellerName: $this->nullableString($this->value($row, $mapping, 'seller_name')),
            sourceUpdatedAt: $this->nullableString($this->value($row, $mapping, 'source_updated_at')),
        );
    }

    /**
     * Feeds spell booleans as 1/0, true/false, yes/no, Y/N or a supplier's own
     * wording. Anything unrecognised stays null: a guessed "not hazardous" on an
     * aerosol is worse than an unanswered field.
     *
     * @param  array<string, mixed>  $settings
     */
    private function nullableBool(mixed $value, array $settings): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        $raw = strtolower(trim((string) $value));
        $map = is_array($settings['boolean_map'] ?? null) ? array_change_key_case($settings['boolean_map']) : [];

        if (array_key_exists($raw, $map)) {
            return (bool) $map[$raw];
        }

        return match ($raw) {
            '1', 'true', 'yes', 'y', 'da', 'ja', 'ano' => true,
            '0', 'false', 'no', 'n', 'nu', 'nein', 'ne' => false,
            default => null,
        };
    }

    /** @param array<string, mixed> $settings */
    private function shippingClass(mixed $value, array $settings): ?string
    {
        $raw = strtolower(trim((string) ($value ?? '')));

        if ($raw === '') {
            return null;
        }

        $map = is_array($settings['shipping_class_map'] ?? null) ? array_change_key_case($settings['shipping_class_map']) : [];

        return $map[$raw] ?? $raw;
    }

    /** @param array<string, mixed> $row */
    private function value(array $row, array $mapping, string $field): mixed
    {
        return data_get($row, $mapping[$field] ?? $field);
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    /** @param array<string, mixed> $settings */
    private function nullableFloat(mixed $value, array $settings): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $value = trim((string) $value);
        $thousands = (string) ($settings['number_thousands_separator'] ?? '');
        $decimal = (string) ($settings['number_decimal_separator'] ?? '.');

        if ($thousands !== '') {
            $value = str_replace($thousands, '', $value);
        }
        if ($decimal !== '.' && $decimal !== '') {
            $value = str_replace($decimal, '.', $value);
        }
        $value = preg_replace('/[^0-9+\-.]/', '', $value) ?? $value;

        return is_numeric($value) ? (float) $value : null;
    }

    /** @param array<string, mixed> $settings */
    private function nullableInt(mixed $value, array $settings): ?int
    {
        $number = $this->nullableFloat($value, $settings);

        return $number !== null ? (int) $number : null;
    }

    /** @param array<string, mixed> $settings */
    private function stockStatus(mixed $value, array $settings): string
    {
        $raw = strtolower(trim((string) ($value ?? '')));
        $map = is_array($settings['stock_status_map'] ?? null) ? $settings['stock_status_map'] : [];

        if ($raw !== '' && array_key_exists($raw, $map)) {
            return (string) $map[$raw];
        }

        return $raw !== '' ? $raw : 'unknown';
    }

    /** @return array<int|string, mixed> */
    private function arrayValue(mixed $value, ?string $delimiter = null): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
            if ($delimiter !== null && $delimiter !== '') {
                return array_values(array_filter(array_map('trim', explode($delimiter, $value)), static fn (string $item): bool => $item !== ''));
            }
        }

        return Arr::wrap($value);
    }

    /** @return array<string, mixed> */
    private function associativeArrayValue(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }
}
