<?php

namespace App\Catalog\Matching;

use App\Catalog\Normalization\IdentifierNormalizer;
use App\Models\CatalogPart;
use App\Models\CatalogPartNumber;
use App\Models\SupplierProduct;
use App\Models\SupplierProductMatchCandidate;
use Illuminate\Support\Collection;

class SupplierCatalogPartMatcher
{
    public function __construct(private readonly IdentifierNormalizer $normalizer) {}

    /** @return Collection<int, array{part:CatalogPart,score:float,reasons:array<int,string>}> */
    public function candidates(SupplierProduct $supplierProduct, int $limit = 10): Collection
    {
        $scores = collect();

        if ($supplierProduct->ean) {
            $compact = $this->normalizer->compact($supplierProduct->ean);
            CatalogPartNumber::query()
                ->with('part')
                ->whereIn('scheme', ['EAN', 'EAN_GTIN', 'GTIN', 'UPC'])
                ->where('number_compact', $compact)
                ->limit(20)
                ->get()
                ->each(fn ($number) => $this->add($scores, $number->part, 100, 'exact_ean'));
        }

        if ($supplierProduct->manufacturer_part_number) {
            $normalized = $this->normalizer->normalize($supplierProduct->manufacturer_part_number);
            $compact = $this->normalizer->compact($supplierProduct->manufacturer_part_number);

            CatalogPart::query()
                ->with('brand')
                ->where(fn ($q) => $q->where('mpn_normalized', $normalized)->orWhereHas('numbers', fn ($n) => $n->whereIn('scheme', ['MPN','IAM'])->where('number_compact', $compact)))
                ->limit(50)
                ->get()
                ->each(function (CatalogPart $part) use ($scores, $supplierProduct): void {
                    $brandMatch = $supplierProduct->raw_brand && $part->brand && $this->normalizer->compact($supplierProduct->raw_brand) === $this->normalizer->compact($part->brand->name);
                    $this->add($scores, $part, $brandMatch ? 98 : 86, $brandMatch ? 'exact_brand_mpn' : 'exact_mpn');
                });
        }

        return $scores->sortByDesc('score')->take($limit)->values();
    }

    public function match(SupplierProduct $supplierProduct): void
    {
        $candidates = $this->candidates($supplierProduct);
        SupplierProductMatchCandidate::query()->where('supplier_product_id', $supplierProduct->id)->delete();

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
        $safeAutomatic = $best && $best['score'] >= 98 && (! $second || ($best['score'] - $second['score']) >= 5);

        if ($safeAutomatic) {
            $supplierProduct->update([
                'catalog_part_id' => $best['part']->id,
                'mapping_confidence' => $best['score'],
                'catalog_mapping_status' => 'mapped_auto',
                'catalog_mapping_reason' => ['reasons' => $best['reasons']],
                'catalog_mapped_at' => now(),
            ]);
            return;
        }

        $supplierProduct->update([
            'catalog_part_id' => null,
            'mapping_confidence' => $best['score'] ?? null,
            'catalog_mapping_status' => $best ? 'candidate' : 'unmatched',
            'catalog_mapping_reason' => $best ? ['reasons' => $best['reasons'], 'candidate_count' => $candidates->count()] : null,
            'catalog_mapped_at' => null,
        ]);
    }

    private function add(Collection $scores, ?CatalogPart $part, float $score, string $reason): void
    {
        if (! $part) { return; }
        $existing = $scores->get($part->id, ['part' => $part, 'score' => 0.0, 'reasons' => []]);
        $existing['score'] = max($existing['score'], $score);
        $existing['reasons'] = array_values(array_unique([...$existing['reasons'], $reason]));
        $scores->put($part->id, $existing);
    }
}
