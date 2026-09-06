<?php

namespace Tests\Feature;

use App\Catalog\Normalization\IdentifierNormalizer;
use App\Catalog\Relations\CatalogPartGraphQuery;
use App\Catalog\Relations\CatalogPartRelationResolver;
use App\Models\Brand;
use App\Models\CatalogPart;
use App\Models\CatalogPartNumber;
use App\Models\CatalogPartRelation;
use App\Models\CatalogSource;
use App\Models\CatalogSourceRecord;
use App\Models\CatalogUnresolvedPartRelation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CatalogPartGraphQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_graph_traversal_follows_only_api_redistributable_edges_and_is_bounded_by_depth(): void
    {
        $open = $this->source('OPEN', true);
        $restricted = $this->source('RESTRICTED', false);
        $a = $this->part('MAHLE', 'OC 123', $open);
        $b = $this->part('MANN-FILTER', 'W 68/3', $open);
        $c = $this->part('BOSCH', '0 451 103 316', $open);
        $hidden = $this->part('PRIVATE', 'SECRET-1', $restricted);

        CatalogPartRelation::query()->create([
            'source_part_id' => $a->id,
            'target_part_id' => $b->id,
            'relation_type' => 'equivalent',
            'is_directed' => false,
            'catalog_source_id' => $open->id,
            'confidence' => 92,
        ]);
        CatalogPartRelation::query()->create([
            'source_part_id' => $b->id,
            'target_part_id' => $c->id,
            'relation_type' => 'superseded_by',
            'is_directed' => true,
            'catalog_source_id' => $open->id,
            'confidence' => 81,
        ]);
        CatalogPartRelation::query()->create([
            'source_part_id' => $a->id,
            'target_part_id' => $hidden->id,
            'relation_type' => 'equivalent',
            'is_directed' => false,
            'catalog_source_id' => $restricted->id,
            'confidence' => 100,
        ]);

        $oneHop = app(CatalogPartGraphQuery::class)->resolve('OC-123', 'MPN', depth: 1);
        $this->assertSameCanonicalIds([$a->id, $b->id], $oneHop['nodes']);
        $this->assertCount(1, $oneHop['edges']);

        $twoHops = app(CatalogPartGraphQuery::class)->resolve('OC 123', 'MPN', depth: 2);
        $this->assertSameCanonicalIds([$a->id, $b->id, $c->id], $twoHops['nodes']);
        $this->assertCount(2, $twoHops['edges']);
        $this->assertFalse($twoHops['truncated']);

        $bNode = collect($twoHops['nodes'])->first(fn (array $node) => $node['part']->id === $b->id);
        $cNode = collect($twoHops['nodes'])->first(fn (array $node) => $node['part']->id === $c->id);
        $this->assertSame(92.0, $bNode['path_confidence']);
        $this->assertSame(81.0, $cNode['path_confidence']);
    }

    public function test_restricted_identifier_cannot_seed_public_graph_resolution(): void
    {
        $restricted = $this->source('PRIVATE', false);
        $part = $this->part('PRIVATE BRAND', 'NO-API-1', $restricted);

        $result = app(CatalogPartGraphQuery::class)->resolve('NO API 1');

        $this->assertTrue($result['seeds']->isEmpty());
        $this->assertSame([], $result['nodes']);
        $this->assertSame([], $result['edges']);
        $this->assertDatabaseHas('catalog_parts', ['id' => $part->id]);
    }

    public function test_manual_resolution_creates_the_same_provenance_bearing_relation_edge(): void
    {
        $source = $this->source('PERMISSIONED', false);
        $sourcePart = $this->part('MAHLE', 'OC 123', $source);
        $targetPart = $this->part('MANN-FILTER', 'W 68/3', $source);
        $record = CatalogSourceRecord::query()->create([
            'catalog_source_id' => $source->id,
            'record_type' => 'part',
            'external_id' => 'row-1',
            'raw_payload' => ['mpn' => 'OC 123'],
            'mapping_status' => 'published',
        ]);
        $pending = CatalogUnresolvedPartRelation::query()->create([
            'source_part_id' => $sourcePart->id,
            'relation_type' => 'equivalent',
            'target_scheme' => 'MPN',
            'target_brand_raw' => 'MANN-FILTER',
            'target_brand_normalized' => 'MANNFILTER',
            'target_number_raw' => 'W 68/3',
            'target_number_normalized' => 'W683',
            'target_number_compact' => 'W683',
            'catalog_source_id' => $source->id,
            'catalog_source_record_id' => $record->id,
            'confidence' => 88,
            'status' => 'pending',
        ]);

        $resolved = app(CatalogPartRelationResolver::class)->resolveTo($pending, $targetPart);

        $this->assertTrue($resolved);
        $pending->refresh();
        $this->assertSame('resolved', $pending->status);
        $this->assertSame($targetPart->id, $pending->resolved_target_part_id);
        $this->assertNotNull($pending->resolved_at);
        $this->assertDatabaseHas('catalog_part_relations', [
            'source_part_id' => $sourcePart->id,
            'target_part_id' => $targetPart->id,
            'relation_type' => 'equivalent',
            'catalog_source_id' => $source->id,
            'catalog_source_record_id' => $record->id,
            'is_directed' => false,
        ]);
    }

    private function source(string $code, bool $apiRedistributable): CatalogSource
    {
        return CatalogSource::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => $code.' source',
            'code' => $code.'_'.Str::upper(Str::random(5)),
            'source_type' => 'manufacturer',
            'protocol' => 'manual',
            'rights_class' => $apiRedistributable ? 'open_redistributable' : 'internal_reference',
            'allow_internal' => true,
            'allow_ecommerce' => true,
            'allow_derived' => true,
            'allow_api_redistribution' => $apiRedistributable,
            'is_active' => true,
        ]);
    }

    private function part(string $brandName, string $mpn, CatalogSource $source): CatalogPart
    {
        $brand = Brand::query()->firstOrCreate(
            ['slug' => Str::slug($brandName)],
            ['name' => $brandName, 'is_active' => true],
        );
        $normalizer = app(IdentifierNormalizer::class);
        $part = CatalogPart::query()->create([
            'public_id' => (string) Str::ulid(),
            'brand_id' => $brand->id,
            'mpn_raw' => $mpn,
            'mpn_normalized' => $normalizer->normalize($mpn),
            'name' => $brandName.' '.$mpn,
            'quality_score' => 100,
        ]);
        CatalogPartNumber::query()->create([
            'catalog_part_id' => $part->id,
            'brand_id' => $brand->id,
            'scheme' => 'MPN',
            'number_raw' => $mpn,
            'number_normalized' => $normalizer->normalize($mpn),
            'number_compact' => $normalizer->compact($mpn),
            'catalog_source_id' => $source->id,
            'confidence' => 100,
        ]);

        return $part;
    }

    /** @param array<int, array<string, mixed>> $nodes */
    private function assertSameCanonicalIds(array $expected, array $nodes): void
    {
        $actual = collect($nodes)->map(fn (array $node) => $node['part']->id)->sort()->values()->all();
        sort($expected);
        $this->assertSame($expected, $actual);
    }
}
