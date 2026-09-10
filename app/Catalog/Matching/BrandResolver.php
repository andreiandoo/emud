<?php

namespace App\Catalog\Matching;

use App\Catalog\Normalization\IdentifierNormalizer;
use App\Models\Brand;
use App\Models\CatalogMappingRule;
use Illuminate\Support\Str;

/**
 * Turns a supplier's brand spelling into a catalogue brand.
 *
 * Brand+MPN is the strongest match most feeds can offer, and it fails silently on
 * spelling: "FEBI" and "febi bilstein" are one manufacturer, "BOSCH" and "Robert
 * Bosch GmbH" too. The slug catches case and punctuation; anything further is an
 * alias an operator confirmed once and never has to confirm again.
 *
 * Aliases live in catalog_mapping_rules with entity_type "brand" rather than in a
 * table of their own, so the canonicalizer can resolve through the same rules
 * later instead of growing a second alias mechanism.
 */
class BrandResolver
{
    public const ENTITY_TYPE = 'brand';

    /** @var array<string, array{brand_id: int, via: string}|null> */
    private array $memo = [];

    public function __construct(private readonly IdentifierNormalizer $normalizer) {}

    /** @return array{brand_id: int, via: string}|null "via" is "name" or "alias". */
    public function resolve(?string $raw): ?array
    {
        if (blank($raw)) {
            return null;
        }

        $key = $this->normalizer->compact((string) $raw);

        if ($key === '') {
            return null;
        }

        if (array_key_exists($key, $this->memo)) {
            return $this->memo[$key];
        }

        $brandId = Brand::query()->where('slug', Str::slug((string) $raw))->value('id');

        if ($brandId) {
            return $this->memo[$key] = ['brand_id' => (int) $brandId, 'via' => 'name'];
        }

        $rule = CatalogMappingRule::query()
            ->where('entity_type', self::ENTITY_TYPE)
            ->where('source_value', $key)
            ->where('is_active', true)
            ->orderBy('priority')
            ->first();

        $aliasedId = data_get($rule?->target, 'brand_id');

        return $this->memo[$key] = is_numeric($aliasedId) ? ['brand_id' => (int) $aliasedId, 'via' => 'alias'] : null;
    }

    /**
     * Records that a supplier spelling means an existing catalogue brand.
     *
     * Global rather than per supplier: "FEBI" is febi bilstein whichever feed says
     * it, and scoping it would mean confirming the same fact once per supplier.
     */
    public function alias(string $raw, int $brandId, ?int $userId = null): CatalogMappingRule
    {
        $key = $this->normalizer->compact($raw);

        $rule = CatalogMappingRule::query()->updateOrCreate(
            ['entity_type' => self::ENTITY_TYPE, 'catalog_source_id' => null, 'source_value' => $key],
            [
                'field' => 'name',
                'match_type' => 'exact',
                'target' => ['brand_id' => $brandId, 'original_spelling' => trim($raw)],
                'confidence' => 100,
                'is_active' => true,
                'created_by' => $userId,
            ],
        );

        unset($this->memo[$key]);

        return $rule;
    }
}
