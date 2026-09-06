<?php

namespace App\Catalog\Relations;

use App\Catalog\Normalization\IdentifierNormalizer;
use App\Models\Brand;
use App\Models\CatalogPart;
use App\Models\CatalogPartNumber;
use App\Models\CatalogPartRelation;
use App\Models\CatalogSourceRecord;
use App\Models\CatalogUnresolvedPartRelation;
use Illuminate\Support\Str;

class CatalogPartRelationResolver
{
    public function __construct(private readonly IdentifierNormalizer $normalizer) {}

    /** @param array<string, mixed> $reference */
    public function capture(
        CatalogSourceRecord $record,
        CatalogPart $sourcePart,
        array $reference,
        string $defaultRelationType,
        float $confidence,
    ): void {
        $number = trim((string) ($reference['number'] ?? ''));
        if ($number === '') {
            return;
        }

        $scheme = strtoupper(trim((string) ($reference['scheme'] ?? 'MPN')) ?: 'MPN');
        $brandRaw = $this->nullableString($reference['brand'] ?? null);
        $relationType = trim((string) ($reference['relation_type'] ?? $reference['type'] ?? $defaultRelationType));
        $relationType = $relationType !== '' ? $relationType : $defaultRelationType;
        $effectiveConfidence = is_numeric($reference['confidence'] ?? null) ? (float) $reference['confidence'] : $confidence;
        $target = $this->resolveTarget($scheme, $number, $brandRaw);

        if ($target && $target->id !== $sourcePart->id) {
            $this->persistResolved($record, $sourcePart, $target, $relationType, $effectiveConfidence);

            return;
        }

        $brandNormalized = $brandRaw ? $this->normalizer->normalize($brandRaw) : null;
        CatalogUnresolvedPartRelation::query()->updateOrCreate([
            'source_part_id' => $sourcePart->id,
            'relation_type' => $relationType,
            'target_scheme' => $scheme,
            'target_brand_normalized' => $brandNormalized,
            'target_number_normalized' => $this->normalizer->normalize($number),
            'catalog_source_id' => $record->catalog_source_id,
        ], [
            'target_brand_raw' => $brandRaw,
            'target_number_raw' => $number,
            'target_number_compact' => $this->normalizer->compact($number),
            'catalog_source_record_id' => $record->id,
            'confidence' => $effectiveConfidence,
            'status' => 'pending',
            'resolved_target_part_id' => null,
            'resolved_at' => null,
            'metadata' => ['reference' => $reference],
        ]);
    }

    public function resolvePending(int $limit = 10000): int
    {
        $resolved = 0;

        CatalogUnresolvedPartRelation::query()
            ->where('status', 'pending')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (CatalogUnresolvedPartRelation $pending) use (&$resolved): void {
                $target = $this->resolveTarget(
                    $pending->target_scheme,
                    $pending->target_number_raw,
                    $pending->target_brand_raw,
                );

                if (! $target || $target->id === $pending->source_part_id) {
                    return;
                }

                $record = $pending->sourceRecord;
                if (! $record || ! $pending->sourcePart) {
                    return;
                }

                $this->persistResolved(
                    $record,
                    $pending->sourcePart,
                    $target,
                    $pending->relation_type,
                    (float) ($pending->confidence ?? 0),
                );

                $pending->update([
                    'status' => 'resolved',
                    'resolved_target_part_id' => $target->id,
                    'resolved_at' => now(),
                ]);
                $resolved++;
            });

        return $resolved;
    }

    private function persistResolved(
        CatalogSourceRecord $record,
        CatalogPart $sourcePart,
        CatalogPart $targetPart,
        string $relationType,
        float $confidence,
    ): CatalogPartRelation {
        return CatalogPartRelation::query()->updateOrCreate([
            'source_part_id' => $sourcePart->id,
            'target_part_id' => $targetPart->id,
            'relation_type' => $relationType,
            'catalog_source_id' => $record->catalog_source_id,
        ], [
            'catalog_source_record_id' => $record->id,
            'is_directed' => $relationType !== 'equivalent',
            'confidence' => $confidence,
        ]);
    }

    private function resolveTarget(string $scheme, string $number, ?string $brandRaw): ?CatalogPart
    {
        $normalized = $this->normalizer->normalize($number);
        $compact = $this->normalizer->compact($number);
        $brand = $brandRaw
            ? Brand::query()->where('slug', Str::slug($brandRaw))->first()
            : null;

        if ($brand && in_array($scheme, ['MPN', 'IAM'], true)) {
            $part = CatalogPart::query()
                ->where('brand_id', $brand->id)
                ->where('mpn_normalized', $normalized)
                ->first();
            if ($part) {
                return $part;
            }
        }

        $numberQuery = CatalogPartNumber::query()
            ->with('part')
            ->where('scheme', $scheme)
            ->where(function ($query) use ($normalized, $compact): void {
                $query->where('number_normalized', $normalized)
                    ->orWhere('number_compact', $compact);
            });

        if ($brand) {
            $numberQuery->where(function ($query) use ($brand): void {
                $query->where('brand_id', $brand->id)->orWhereNull('brand_id');
            });
        }

        return $numberQuery->first()?->part;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
