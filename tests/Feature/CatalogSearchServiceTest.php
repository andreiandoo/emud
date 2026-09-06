<?php

namespace Tests\Feature;

use App\Catalog\Search\CatalogSearchService;
use App\Models\CatalogPart;
use App\Models\CatalogPartNumber;
use App\Models\CatalogSearchDocument;
use App\Models\CatalogSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CatalogSearchServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_null_when_projection_has_not_been_built_for_an_entity_type(): void
    {
        $this->assertNull(app(CatalogSearchService::class)->entityIds('catalog_part', 'brake pad'));
    }

    public function test_it_searches_projected_documents_and_returns_ranked_entity_ids(): void
    {
        CatalogSearchDocument::query()->create([
            'entity_type' => 'catalog_part',
            'entity_id' => 101,
            'title' => 'Brembo brake pad',
            'search_text' => 'Brembo brake pad P85020 front axle',
            'indexed_at' => now(),
        ]);
        CatalogSearchDocument::query()->create([
            'entity_type' => 'catalog_part',
            'entity_id' => 202,
            'title' => 'Oil filter',
            'search_text' => 'MANN oil filter HU7197X',
            'indexed_at' => now(),
        ]);

        $ids = app(CatalogSearchService::class)->entityIds('catalog_part', 'Brembo brake');

        $this->assertSame([101], $ids);
    }

    public function test_exact_part_number_search_ignores_numbers_from_non_redistributable_sources(): void
    {
        $publicSource = $this->source('PUBLIC', true);
        $restrictedSource = $this->source('RESTRICTED', false);
        $publicPart = $this->part('PUBLIC-BASE');
        $restrictedPart = $this->part('RESTRICTED-BASE');

        CatalogPartNumber::query()->create([
            'catalog_part_id' => $publicPart->id,
            'scheme' => 'MPN',
            'number_raw' => 'ABC-123',
            'number_normalized' => 'ABC-123',
            'number_compact' => 'ABC123',
            'catalog_source_id' => $publicSource->id,
        ]);
        CatalogPartNumber::query()->create([
            'catalog_part_id' => $restrictedPart->id,
            'scheme' => 'OEM',
            'number_raw' => 'SECRET-999',
            'number_normalized' => 'SECRET-999',
            'number_compact' => 'SECRET999',
            'catalog_source_id' => $restrictedSource->id,
        ]);

        $service = app(CatalogSearchService::class);

        $this->assertSame([$publicPart->id], $service->partIds('ABC-123'));
        $this->assertNull($service->partIds('SECRET-999'));
    }

    private function source(string $code, bool $redistributable): CatalogSource
    {
        return CatalogSource::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => $code,
            'code' => $code,
            'source_type' => 'test',
            'rights_class' => 'unknown_pending_review',
            'allow_api_redistribution' => $redistributable,
            'is_active' => true,
        ]);
    }

    private function part(string $mpn): CatalogPart
    {
        return CatalogPart::query()->create([
            'public_id' => (string) Str::ulid(),
            'mpn_raw' => $mpn,
            'mpn_normalized' => $mpn,
            'name' => 'Test part '.$mpn,
        ]);
    }
}
