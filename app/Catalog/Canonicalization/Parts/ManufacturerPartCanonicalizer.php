<?php

namespace App\Catalog\Canonicalization\Parts;

use App\Catalog\Canonicalization\CanonicalizationResult;
use App\Catalog\Canonicalization\Contracts\CatalogRecordCanonicalizer;
use App\Catalog\Canonicalization\SourceAssertionWriter;
use App\Catalog\Normalization\IdentifierNormalizer;
use App\Models\Attribute;
use App\Models\Brand;
use App\Models\CatalogChangeEvent;
use App\Models\CatalogConflict;
use App\Models\CatalogFitment;
use App\Models\CatalogMappingRule;
use App\Models\CatalogPart;
use App\Models\CatalogPartAttribute;
use App\Models\CatalogPartNumber;
use App\Models\CatalogSourceRecord;
use App\Models\Category;
use App\Models\VehicleConfiguration;
use App\Models\VehicleIdentifier;
use App\Models\VehicleMake;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ManufacturerPartCanonicalizer implements CatalogRecordCanonicalizer
{
    public function __construct(
        private readonly IdentifierNormalizer $normalizer,
        private readonly SourceAssertionWriter $assertions,
    ) {}

    public function canonicalize(CatalogSourceRecord $record): CanonicalizationResult
    {
        $record->loadMissing('source');
        $row = $record->raw_payload ?? [];
        $mapping = $record->source->field_mapping ?? [];

        $brandName = $this->stringValue($row, $mapping, 'brand');
        $mpnRaw = $this->stringValue($row, $mapping, 'mpn');
        if (! $brandName || ! $mpnRaw) {
            return CanonicalizationResult::skipped('Manufacturer part record requires brand and MPN.');
        }

        $brand = Brand::query()->firstOrCreate(
            ['slug' => Str::slug($brandName)],
            ['name' => trim($brandName), 'is_active' => true],
        );
        $category = $this->resolveCategory($record, $row, $mapping);
        $mpnNormalized = $this->normalizer->normalize($mpnRaw);
        $name = $this->stringValue($row, $mapping, 'name');
        $description = $this->stringValue($row, $mapping, 'description');

        $part = CatalogPart::query()->firstOrNew([
            'brand_id' => $brand->id,
            'mpn_normalized' => $mpnNormalized,
        ]);
        $created = ! $part->exists;

        if ($created) {
            $part->public_id = (string) Str::ulid();
            $part->mpn_raw = $mpnRaw;
            $part->category_id = $category?->id;
            $part->name = $name;
            $part->description = $description;
            $part->lifecycle_status = $this->stringValue($row, $mapping, 'lifecycle_status') ?: 'active';
            $part->quality_score = (float) ($record->source->settings['part_confidence'] ?? 95);
            $part->save();
        } else {
            $updates = [];
            if (! $part->category_id && $category) { $updates['category_id'] = $category->id; }
            if (! $part->name && $name) { $updates['name'] = $name; }
            if (! $part->description && $description) { $updates['description'] = $description; }
            if ($updates) { $part->update($updates); }
        }

        $confidence = (float) ($record->source->settings['part_confidence'] ?? 95);
        $this->upsertNumber($record, $part, 'MPN', $mpnRaw, $brand->id, null, $confidence);

        $ean = $this->stringValue($row, $mapping, 'ean');
        if ($ean) {
            $this->upsertNumber($record, $part, 'EAN_GTIN', $ean, null, null, $confidence);
        }

        foreach ($this->referenceList($this->value($row, $mapping, 'oe_numbers')) as $reference) {
            $makeId = $this->resolveOeMake($reference['make'] ?? null);
            $this->upsertNumber($record, $part, strtoupper($reference['scheme'] ?? 'OE'), (string) $reference['number'], null, $makeId, $confidence);
        }

        foreach ($this->referenceList($this->value($row, $mapping, 'iam_numbers')) as $reference) {
            $this->upsertNumber($record, $part, strtoupper($reference['scheme'] ?? 'IAM'), (string) $reference['number'], null, null, $confidence);
        }

        $this->upsertAttributes($record, $part, $this->value($row, $mapping, 'attributes'));
        $this->upsertFitments($record, $part, $this->value($row, $mapping, 'fitments'));

        $this->assertions->write($record, 'catalog_part', $part->id, [
            'brand' => $brandName,
            'mpn' => $mpnRaw,
            'name' => $name,
            'description' => $description,
            'category_id' => $category?->id,
        ], $confidence);

        CatalogChangeEvent::query()->create([
            'public_id' => (string) Str::ulid(),
            'event_type' => $created ? 'part.created' : 'part.updated',
            'entity_type' => 'catalog_part',
            'entity_id' => $part->id,
            'payload' => ['part_id' => (string) $part->public_id, 'source' => $record->source->code],
            'api_redistributable' => (bool) $record->source->allow_api_redistribution,
            'occurred_at' => now(),
        ]);

        return CanonicalizationResult::published('catalog_part', $part->id, $confidence);
    }

    private function resolveCategory(CatalogSourceRecord $record, array $row, array $mapping): ?Category
    {
        $categoryId = $this->value($row, $mapping, 'category_id');
        if (is_numeric($categoryId)) {
            return Category::query()->find((int) $categoryId);
        }

        $sourceCategory = $this->stringValue($row, $mapping, 'category');
        if (! $sourceCategory) {
            return null;
        }

        $rule = CatalogMappingRule::query()
            ->where(fn ($query) => $query->whereNull('catalog_source_id')->orWhere('catalog_source_id', $record->catalog_source_id))
            ->where('entity_type', 'category')
            ->where('source_value', $sourceCategory)
            ->where('is_active', true)
            ->orderBy('priority')
            ->first();
        if ($rule && is_numeric(data_get($rule->target, 'category_id'))) {
            return Category::query()->find((int) data_get($rule->target, 'category_id'));
        }

        return Category::query()
            ->where('full_path', 'ilike', $sourceCategory)
            ->orWhere('name', 'ilike', $sourceCategory)
            ->first();
    }

    private function upsertNumber(CatalogSourceRecord $record, CatalogPart $part, string $scheme, string $raw, ?int $brandId, ?int $oeMakeId, float $confidence): void
    {
        $normalized = $this->normalizer->normalize($raw);
        CatalogPartNumber::query()->updateOrCreate([
            'catalog_part_id' => $part->id,
            'scheme' => $scheme,
            'number_normalized' => $normalized,
            'catalog_source_id' => $record->catalog_source_id,
        ], [
            'brand_id' => $brandId,
            'oe_make_id' => $oeMakeId,
            'number_raw' => $raw,
            'number_compact' => $this->normalizer->compact($raw),
            'confidence' => $confidence,
        ]);
    }

    private function upsertAttributes(CatalogSourceRecord $record, CatalogPart $part, mixed $rawAttributes): void
    {
        if (! is_array($rawAttributes)) {
            return;
        }

        foreach ($rawAttributes as $key => $value) {
            $attribute = Attribute::query()->where('code', (string) $key)->first();
            if (! $attribute) {
                continue;
            }

            $payload = ['catalog_source_id' => $record->catalog_source_id, 'confidence' => (float) ($record->source->settings['attribute_confidence'] ?? 95)];
            if (is_numeric($value) && $attribute->type === 'number') {
                $payload['value_number'] = $value;
            } elseif (is_array($value)) {
                $payload['value_json'] = $value;
            } else {
                $payload['value_text'] = is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            CatalogPartAttribute::query()->updateOrCreate([
                'catalog_part_id' => $part->id,
                'attribute_id' => $attribute->id,
                'catalog_source_id' => $record->catalog_source_id,
            ], $payload);
        }
    }

    private function upsertFitments(CatalogSourceRecord $record, CatalogPart $part, mixed $rawFitments): void
    {
        if (! is_array($rawFitments)) {
            return;
        }

        foreach ($rawFitments as $index => $fitmentRow) {
            if (! is_array($fitmentRow)) {
                continue;
            }

            $configuration = $this->resolveConfiguration($fitmentRow);
            if (! $configuration) {
                CatalogConflict::query()->firstOrCreate([
                    'entity_type' => 'catalog_part',
                    'entity_id' => $part->id,
                    'field_or_relation' => 'fitment_unresolved',
                    'status' => 'open',
                    'details' => ['source_record_id' => $record->id, 'fitment' => $fitmentRow],
                ], ['severity' => 'warning']);
                continue;
            }

            $sourceKey = (string) ($fitmentRow['id'] ?? hash('sha256', json_encode([$record->external_id, $index, $fitmentRow], JSON_THROW_ON_ERROR)));
            $confidence = (float) ($fitmentRow['confidence'] ?? $record->source->settings['fitment_confidence'] ?? 98);
            $fitment = CatalogFitment::query()->updateOrCreate([
                'catalog_part_id' => $part->id,
                'configuration_id' => $configuration->id,
                'catalog_source_id' => $record->catalog_source_id,
                'source_fitment_key' => $sourceKey,
            ], [
                'category_id' => $part->category_id,
                'position' => $fitmentRow['position'] ?? null,
                'valid_from' => $fitmentRow['valid_from'] ?? null,
                'valid_to' => $fitmentRow['valid_to'] ?? null,
                'status' => $fitmentRow['status'] ?? 'confirmed',
                'confidence' => $confidence,
            ]);

            $fitment->constraints()->delete();
            foreach (($fitmentRow['constraints'] ?? []) as $constraint) {
                if (! is_array($constraint) || empty($constraint['type'])) { continue; }
                $fitment->constraints()->create([
                    'constraint_type' => $constraint['type'],
                    'operator' => $constraint['operator'] ?? null,
                    'value_text' => isset($constraint['value']) && is_scalar($constraint['value']) ? (string) $constraint['value'] : null,
                    'value_number' => isset($constraint['value']) && is_numeric($constraint['value']) ? $constraint['value'] : null,
                    'unit' => $constraint['unit'] ?? null,
                    'normalized' => $constraint['normalized'] ?? null,
                    'display_text' => $constraint['display_text'] ?? null,
                    'catalog_source_id' => $record->catalog_source_id,
                ]);
            }
        }
    }

    private function resolveConfiguration(array $fitmentRow): ?VehicleConfiguration
    {
        if (is_numeric($fitmentRow['configuration_id'] ?? null)) {
            return VehicleConfiguration::query()->find((int) $fitmentRow['configuration_id']);
        }

        $scheme = $fitmentRow['vehicle_identifier_scheme'] ?? null;
        $value = $fitmentRow['vehicle_identifier'] ?? null;
        if ($scheme && $value) {
            $normalized = $this->normalizer->normalize((string) $value);
            $identifier = VehicleIdentifier::query()
                ->where('scheme', strtoupper((string) $scheme))
                ->where('value_normalized', $normalized)
                ->first();
            return $identifier?->configuration;
        }

        return null;
    }

    private function resolveOeMake(mixed $make): ?int
    {
        if (! is_string($make) || trim($make) === '') {
            return null;
        }
        return VehicleMake::query()->where('name', 'ilike', trim($make))->value('id');
    }

    private function referenceList(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        if (is_string($value)) {
            return [['number' => $value]];
        }
        if (! is_array($value)) {
            return [];
        }

        $result = [];
        foreach (array_is_list($value) ? $value : [$value] as $item) {
            if (is_string($item) || is_numeric($item)) {
                $result[] = ['number' => (string) $item];
            } elseif (is_array($item) && isset($item['number'])) {
                $result[] = $item;
            }
        }
        return $result;
    }

    private function stringValue(array $row, array $mapping, string $field): ?string
    {
        $value = $this->value($row, $mapping, $field);
        return is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null;
    }

    private function value(array $row, array $mapping, string $field): mixed
    {
        return Arr::get($row, $mapping[$field] ?? $field);
    }
}
