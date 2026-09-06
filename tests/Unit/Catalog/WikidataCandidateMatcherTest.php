<?php

namespace Tests\Unit\Catalog;

use App\Catalog\Enrichment\Wikidata\WikidataCandidateMatcher;
use PHPUnit\Framework\TestCase;

class WikidataCandidateMatcherTest extends TestCase
{
    public function test_it_accepts_an_exact_automobile_manufacturer_candidate(): void
    {
        $result = (new WikidataCandidateMatcher)->match([
            ['id' => 'Q123', 'label' => 'Jeep', 'description' => 'American automobile manufacturer', 'match' => ['text' => 'Jeep']],
            ['id' => 'Q456', 'label' => 'Jeep', 'description' => 'song', 'match' => ['text' => 'Jeep']],
        ], 'vehicle_make', 'Jeep');

        $this->assertSame('matched', $result['status']);
        $this->assertSame('Q123', $result['candidate']['id']);
        $this->assertGreaterThanOrEqual(90, $result['confidence']);
    }

    public function test_it_uses_make_context_for_vehicle_models(): void
    {
        $result = (new WikidataCandidateMatcher)->match([
            ['id' => 'Q1', 'label' => 'Wrangler', 'description' => 'Jeep sport utility vehicle model', 'match' => ['text' => 'Wrangler']],
            ['id' => 'Q2', 'label' => 'Wrangler', 'description' => 'brand of jeans', 'match' => ['text' => 'Wrangler']],
        ], 'vehicle_model', 'Wrangler', 'Jeep');

        $this->assertSame('matched', $result['status']);
        $this->assertSame('Q1', $result['candidate']['id']);
    }

    public function test_it_does_not_auto_publish_weak_generation_matches(): void
    {
        $result = (new WikidataCandidateMatcher)->match([
            ['id' => 'Q3', 'label' => 'JL', 'description' => 'Jeep vehicle generation', 'match' => ['text' => 'JL']],
        ], 'vehicle_generation', 'Wrangler JL', 'Jeep');

        $this->assertSame('skipped', $result['status']);
    }
}
