<?php

namespace Tests\Unit\Catalog;

use App\Catalog\Enrichment\Wikidata\WikidataClient;
use App\Catalog\Enrichment\Wikidata\WikidataRateLimited;
use App\Models\CatalogSource;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WikidataClientTest extends TestCase
{
    public function test_it_searches_and_fetches_entities_with_identifiable_requests(): void
    {
        Http::fake([
            'https://www.wikidata.org/w/api.php*' => Http::sequence()
                ->push(['search' => [['id' => 'Q123', 'label' => 'Jeep', 'description' => 'automobile manufacturer']]])
                ->push(['entities' => ['Q123' => ['id' => 'Q123', 'labels' => ['en' => ['value' => 'Jeep']], 'aliases' => []]]]),
        ]);
        $source = new CatalogSource(['settings' => ['user_agent' => 'eMUD/1.0 (https://github.com/andreiandoo/emud)']]);
        $client = new WikidataClient;

        $search = $client->search($source, 'Jeep');
        $entity = $client->entity($source, 'Q123', ['en', 'ro']);

        $this->assertSame('Q123', $search[0]['id']);
        $this->assertSame('Jeep', data_get($entity, 'labels.en.value'));
        Http::assertSent(fn ($request) => $request->hasHeader('User-Agent', 'eMUD/1.0 (https://github.com/andreiandoo/emud)'));
    }

    public function test_it_surfaces_retry_after_on_rate_limit(): void
    {
        Http::fake(['https://www.wikidata.org/w/api.php*' => Http::response([], 429, ['Retry-After' => '17'])]);
        $source = new CatalogSource(['settings' => ['user_agent' => 'eMUD/1.0 (https://github.com/andreiandoo/emud)']]);

        $this->expectException(WikidataRateLimited::class);
        try {
            (new WikidataClient)->search($source, 'Jeep');
        } catch (WikidataRateLimited $exception) {
            $this->assertSame(17, $exception->retryAfterSeconds);
            throw $exception;
        }
    }
}
