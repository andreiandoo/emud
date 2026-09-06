<?php

namespace Tests\Feature;

use App\Catalog\Normalization\IdentifierNormalizer;
use App\Catalog\Relations\CatalogPartRelationResolver;
use App\Models\Brand;
use App\Models\CatalogPart;
use App\Models\CatalogPartNumber;
use App\Models\CatalogSource;
use App\Models\CatalogSourceRecord;
use App\Models\CatalogUnresolvedPartRelation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CatalogRelationResolverQaTest extends TestCase
{
    use RefreshDatabase;

    public function test_automatic_retries_are_audited_until_the_target_arrives(): void
    {
        $source = $this->source();
        $sourcePart = $this->part('MAHLE', 'OC 123');
        $record = $this->record($source);
        $pending = $this->pending($source, $record, $sourcePart, 'MANN-FILTER', 'W 68/3');
        $resolver = app(CatalogPartRelationResolver::class);

        $this->assertFalse($resolver->resolveOne($pending));
        $pending->refresh();
        $this->assertSame(1, $pending->resolution_attempts);
        $this->assertNotNull($pending->last_resolution_attempt_at);
        $this->assertSame('pending', $pending->status);

        $target = $this->part('MANN-FILTER', 'W 68/3', $source);

        $this->assertTrue($resolver->resolveOne($pending->fresh()));
        $pending->refresh();
        $this->assertSame(2, $pending->resolution_attempts);
        $this->assertSame('resolved', $pending->status);
        $this->assertSame($target->id, $pending->resolved_target_part_id);
        $this->assertDatabaseHas('catalog_part_relations', [
            'source_part_id' => $sourcePart->id,
            'target_part_id' => $target->id,
            'relation_type' => 'equivalent',
            'catalog_source_id' => $source->id,
        ]);
    }

    public function test_manual_resolution_records_the_reviewer_without_mutating_source_evidence(): void
    {
        $source = $this->source();
        $sourcePart = $this->part('MAHLE', 'OC 123');
        $target = $this->part('MANN-FILTER', 'W 68/3');
        $record = $this->record($source);
        $pending = $this->pending($source, $record, $sourcePart, 'MANN-FILTER', 'legacy-number');
        $reviewer = User::factory()->create();

        $resolved = app(CatalogPartRelationResolver::class)->resolveTo(
            $pending,
            $target,
            $reviewer->id,
            'Matched against manufacturer application sheet.',
        );

        $this->assertTrue($resolved);
        $pending->refresh();
        $this->assertSame('resolved', $pending->status);
        $this->assertSame($target->id, $pending->resolved_target_part_id);
        $this->assertSame($reviewer->id, $pending->reviewed_by);
        $this->assertNotNull($pending->reviewed_at);
        $this->assertSame('Matched against manufacturer application sheet.', $pending->review_note);
        $this->assertSame('legacy-number', $pending->target_number_raw);
        $this->assertSame('legacy-number', $record->fresh()->raw_payload['reference']);
    }

    private function source(): CatalogSource
    {
        return CatalogSource::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'Permissioned manufacturer',
            'code' => 'QA_'.Str::upper(Str::random(6)),
            'source_type' => 'manufacturer',
            'protocol' => 'manual',
            'rights_class' => 'permissioned_redistributable',
            'allow_internal' => true,
            'allow_ecommerce' => true,
            'allow_derived' => true,
            'allow_api_redistribution' => false,
            'is_active' => true,
        ]);
    }

    private function part(string $brandName, string $mpn, ?CatalogSource $source = null): CatalogPart
    {
        $normalizer = app(IdentifierNormalizer::class);
        $brand = Brand::query()->firstOrCreate(
            ['slug' => Str::slug($brandName)],
            ['name' => $brandName, 'is_active' => true],
        );
        $part = CatalogPart::query()->create([
            'public_id' => (string) Str::ulid(),
            'brand_id' => $brand->id,
            'mpn_raw' => $mpn,
            'mpn_normalized' => $normalizer->normalize($mpn),
            'name' => $brandName.' part',
            'lifecycle_status' => 'active',
            'quality_score' => 100,
        ]);

        if ($source) {
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
        }

        return $part;
    }

    private function record(CatalogSource $source): CatalogSourceRecord
    {
        return CatalogSourceRecord::query()->create([
            'catalog_source_id' => $source->id,
            'record_type' => 'part',
            'external_id' => 'REC-'.Str::upper(Str::random(8)),
            'raw_payload' => ['reference' => 'legacy-number'],
            'mapping_status' => 'published',
            'last_seen_at' => now(),
        ]);
    }

    private function pending(
        CatalogSource $source,
        CatalogSourceRecord $record,
        CatalogPart $sourcePart,
        string $targetBrand,
        string $targetNumber,
    ): CatalogUnresolvedPartRelation {
        $normalizer = app(IdentifierNormalizer::class);

        return CatalogUnresolvedPartRelation::query()->create([
            'source_part_id' => $sourcePart->id,
            'relation_type' => 'equivalent',
            'target_scheme' => 'MPN',
            'target_brand_raw' => $targetBrand,
            'target_brand_normalized' => $normalizer->normalize($targetBrand),
            'target_number_raw' => $targetNumber,
            'target_number_normalized' => $normalizer->normalize($targetNumber),
            'target_number_compact' => $normalizer->compact($targetNumber),
            'catalog_source_id' => $source->id,
            'catalog_source_record_id' => $record->id,
            'confidence' => 95,
            'status' => 'pending',
        ]);
    }
}
