<?php

namespace App\Catalog\Matching;

use App\Catalog\Normalization\GtinValidator;
use App\Catalog\Normalization\IdentifierNormalizer;
use App\Enums\SupplierIdentifierType;
use App\Models\CatalogPart;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\SupplierProductMatchCandidate;
use App\Suppliers\Data\SupplierRecord;
use App\Suppliers\SupplierIdentifierSync;
use Illuminate\Support\Collection;

/**
 * Maps a supplier article onto the canonical part it is, never onto one it resembles.
 *
 * The ladder, strongest first:
 *
 *   100  TecDoc article or GTIN held by exactly one part
 *    98  brand + MPN, with the brand resolved directly or through a confirmed alias
 *    90  same brand, and the part carries one of the article's OE/IAM references
 *    86  MPN matches but the supplier's brand is unknown to the catalogue
 *    85  same brand, renumbered (supersession)
 *    70  a GTIN shared by several parts — a barcode, but not an identity
 *    55  MPN matches a part of a different manufacturer
 *    50  a shared reference with no brand to corroborate it
 *
 * Only 98 and above ever maps automatically, and only when a single part reaches
 * it. Two parts at that level is a conflict for a human, not a tie to break.
 *
 * A cross reference is deliberately not an identity. A Filtron filter listing
 * "MANN W 712/75" as its equivalent is still a Filtron filter; mapping it onto the
 * MANN part would sell one manufacturer's product under another's name. References
 * only count here when the brand agrees.
 */
class SupplierCatalogPartMatcher
{
    public const AUTO_THRESHOLD = 98.0;

    public const AUTO_GAP = 5.0;

    public function __construct(
        private readonly IdentifierNormalizer $normalizer,
        private readonly GtinValidator $gtins,
        private readonly BrandResolver $brands,
        private readonly SupplierIdentifierSync $identifierSync,
    ) {}

    /** @return Collection<int, array{part: CatalogPart, score: float, reasons: list<string>}> */
    public function candidates(SupplierProduct $supplierProduct, int $limit = 10): Collection
    {
        $identifiers = $this->identifiers($supplierProduct);
        $brand = $this->brands->resolve($supplierProduct->raw_brand);
        $scores = collect();

        foreach ($identifiers->where('type', SupplierIdentifierType::TecDocArticle->value) as $row) {
            foreach ($this->partsByNumber(SupplierIdentifierType::TecDocArticle->catalogSchemes(), [$row['compact_value']]) as $part) {
                // TecDoc article numbers are only unique within a data supplier, so a
                // hit on another brand's article is a coincidence, not the same part.
                match ($this->brandFit($brand, $part)) {
                    'same' => $this->add($scores, $part, 100, 'exact_tecdoc'),
                    'unknown' => $this->add($scores, $part, 90, 'tecdoc_unconfirmed_brand'),
                    'mismatch' => null,
                };
            }
        }

        foreach ($identifiers->where('type', SupplierIdentifierType::Gtin->value) as $row) {
            $parts = $this->partsByNumber(SupplierIdentifierType::Gtin->catalogSchemes(), $this->gtins->lookupForms($row['compact_value']));
            $shared = $parts->count() > 1;

            foreach ($parts as $part) {
                $this->add($scores, $part, $shared ? 70 : 100, $shared ? 'shared_gtin' : 'exact_gtin');
            }
        }

        foreach ($identifiers->where('type', SupplierIdentifierType::Mpn->value) as $row) {
            foreach ($this->partsByMpn($row) as $part) {
                match ($this->brandFit($brand, $part)) {
                    'same' => $this->add($scores, $part, 98, 'exact_brand_mpn', $brand['via'] === 'alias' ? 'brand_alias' : null),
                    'unknown' => $this->add($scores, $part, 86, 'exact_mpn'),
                    'mismatch' => $this->add($scores, $part, 55, 'mpn_brand_mismatch'),
                };
            }
        }

        foreach ([SupplierIdentifierType::Oe, SupplierIdentifierType::Iam, SupplierIdentifierType::CrossReference] as $type) {
            foreach ($identifiers->where('type', $type->value) as $row) {
                foreach ($this->partsByNumber($type->catalogSchemes(), [$row['compact_value']]) as $part) {
                    match ($this->brandFit($brand, $part)) {
                        'same' => $this->add($scores, $part, 90, 'brand_and_reference'),
                        'unknown' => $this->add($scores, $part, 50, 'shared_reference'),
                        // A reference shared with another manufacturer's part marks an
                        // equivalent. It must never be offered as the same article.
                        'mismatch' => null,
                    };
                }
            }
        }

        foreach ($identifiers->where('type', SupplierIdentifierType::Supersession->value) as $row) {
            foreach ($this->partsByNumber(SupplierIdentifierType::Supersession->catalogSchemes(), [$row['compact_value']]) as $part) {
                if ($this->brandFit($brand, $part) === 'same') {
                    $this->add($scores, $part, 85, 'supersession');
                }
            }
        }

        // An operator's rejection is a fact about this pair. Re-running the matcher
        // must not bring the same wrong part back as a fresh suggestion.
        $rejected = $supplierProduct->catalogCandidates()->where('status', 'rejected')->pluck('catalog_part_id')->all();

        return $scores
            ->reject(fn (array $candidate): bool => in_array($candidate['part']->id, $rejected, true))
            ->sortByDesc('score')
            ->take($limit)
            ->values();
    }

    /**
     * @return array{status: string, reasons: list<string>, invalid_gtin: bool}
     */
    public function match(SupplierProduct $supplierProduct): array
    {
        // A human decision is final until a human changes it.
        if ($supplierProduct->catalog_mapping_status === 'mapped_manual') {
            return ['status' => 'mapped_manual', 'reasons' => [], 'invalid_gtin' => false];
        }

        $candidates = $this->candidates($supplierProduct);

        SupplierProductMatchCandidate::query()
            ->where('supplier_product_id', $supplierProduct->id)
            ->where('status', '!=', 'rejected')
            ->delete();

        foreach ($candidates as $candidate) {
            SupplierProductMatchCandidate::query()->create([
                'supplier_product_id' => $supplierProduct->id,
                'catalog_part_id' => $candidate['part']->id,
                'score' => $candidate['score'],
                'reasons' => $candidate['reasons'],
            ]);
        }

        $best = $candidates->first();
        $second = $candidates->skip(1)->first();
        $atAutoLevel = $candidates->filter(fn (array $candidate): bool => $candidate['score'] >= self::AUTO_THRESHOLD);
        $invalidGtin = filled($supplierProduct->ean) && ! $this->gtins->isValid($supplierProduct->ean);

        $status = match (true) {
            $best === null => 'unmatched',
            // Two parts each claimed with full confidence — an EAN pointing one way and
            // brand+MPN the other — means one of the identifiers is wrong.
            $atAutoLevel->count() > 1 => 'conflict',
            $best['score'] >= self::AUTO_THRESHOLD && ($second === null || $best['score'] - $second['score'] >= self::AUTO_GAP) => 'mapped_auto',
            default => 'candidate',
        };

        $reason = array_filter([
            'reasons' => $best['reasons'] ?? [],
            'candidate_count' => $candidates->count(),
            'conflicting_parts' => $status === 'conflict' ? $atAutoLevel->map(fn (array $candidate): int => $candidate['part']->id)->values()->all() : null,
            'invalid_gtin' => $invalidGtin ? $supplierProduct->ean : null,
        ], static fn (mixed $value): bool => $value !== null && $value !== [] && $value !== 0);

        $supplierProduct->update([
            'catalog_part_id' => $status === 'mapped_auto' ? $best['part']->id : null,
            'mapping_confidence' => $best['score'] ?? null,
            'catalog_mapping_status' => $status,
            'catalog_mapping_reason' => $reason === [] ? null : $reason,
            'catalog_mapped_at' => $status === 'mapped_auto' ? now() : null,
        ]);

        return ['status' => $status, 'reasons' => $best['reasons'] ?? [], 'invalid_gtin' => $invalidGtin];
    }

    /**
     * Identifiers recorded at import, or for an article imported before they were,
     * the same set derived from what its row kept.
     *
     * @return Collection<int, array{type: string, value: string, compact_value: string, brand: ?string}>
     */
    private function identifiers(SupplierProduct $supplierProduct): Collection
    {
        $stored = $supplierProduct->identifiers()->get();

        if ($stored->isNotEmpty()) {
            return $stored->map(fn ($row): array => [
                'type' => $row->type->value,
                'value' => $row->value,
                'compact_value' => $row->compact_value,
                'brand' => $row->brand,
            ]);
        }

        return collect($this->identifierSync->rowsFor($supplierProduct->supplier ?? new Supplier, $this->recordFrom($supplierProduct)));
    }

    private function recordFrom(SupplierProduct $supplierProduct): SupplierRecord
    {
        $technical = $supplierProduct->technical_payload ?? [];

        return new SupplierRecord(
            externalId: (string) $supplierProduct->external_id,
            name: (string) $supplierProduct->name,
            sku: $supplierProduct->supplier_sku,
            ean: $supplierProduct->ean,
            manufacturerPartNumber: $supplierProduct->manufacturer_part_number,
            brand: $supplierProduct->raw_brand,
            oeNumbers: (array) ($technical['oe_numbers'] ?? []),
            iamNumbers: (array) ($technical['iam_numbers'] ?? []),
            crossReferences: (array) ($technical['cross_references'] ?? []),
            supersessions: (array) ($technical['supersessions'] ?? []),
        );
    }

    /**
     * @param  list<string>  $schemes
     * @param  list<string>  $compacts
     * @return Collection<int, CatalogPart>
     */
    private function partsByNumber(array $schemes, array $compacts): Collection
    {
        if ($schemes === [] || $compacts === []) {
            return collect();
        }

        return CatalogPart::query()
            ->with('brand')
            ->whereHas('numbers', fn ($query) => $query->whereIn('scheme', $schemes)->whereIn('number_compact', $compacts))
            ->limit(20)
            ->get();
    }

    /**
     * MPN rows only. The old matcher also searched IAM numbers here, which offered
     * another manufacturer's part whenever it listed the supplier's number as an
     * equivalent — the exact mismatch this class exists to avoid.
     *
     * @param  array{type: string, value: string, compact_value: string, brand: ?string}  $row
     * @return Collection<int, CatalogPart>
     */
    private function partsByMpn(array $row): Collection
    {
        return CatalogPart::query()
            ->with('brand')
            ->where(fn ($query) => $query
                ->whereHas('numbers', fn ($numbers) => $numbers->where('scheme', 'MPN')->where('number_compact', $row['compact_value']))
                ->orWhere('mpn_normalized', $this->normalizer->normalize($row['value'])))
            ->limit(50)
            ->get();
    }

    /** @param array{brand_id: int, via: string}|null $brand */
    private function brandFit(?array $brand, CatalogPart $part): string
    {
        if ($brand === null || $part->brand_id === null) {
            return 'unknown';
        }

        return (int) $part->brand_id === $brand['brand_id'] ? 'same' : 'mismatch';
    }

    private function add(Collection $scores, CatalogPart $part, float $score, string $reason, ?string $extraReason = null): void
    {
        $existing = $scores->get($part->id, ['part' => $part, 'score' => 0.0, 'reasons' => []]);
        $existing['score'] = max($existing['score'], $score);
        $existing['reasons'] = array_values(array_unique(array_filter([...$existing['reasons'], $reason, $extraReason])));
        $scores->put($part->id, $existing);
    }
}
