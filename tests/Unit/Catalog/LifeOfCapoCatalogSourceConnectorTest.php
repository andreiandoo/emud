<?php

namespace Tests\Unit\Catalog;

use App\Catalog\Sources\Connectors\LifeOfCapoCatalogSourceConnector;
use App\Models\CatalogSource;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LifeOfCapoCatalogSourceConnectorTest extends TestCase
{
    public function test_it_pins_raw_files_to_the_resolved_upstream_commit_and_flattens_records(): void
    {
        Http::fake([
            'https://api.github.com/repos/lifeofcapo/car-api/commits/main' => Http::response([
                'sha' => 'abc123',
                'commit' => ['committer' => ['date' => '2026-08-11T10:00:00Z']],
            ]),
            'https://raw.githubusercontent.com/lifeofcapo/car-api/abc123/car-brands.json' => Http::response([
                [
                    'brand' => 'Abarth',
                    'models' => [[
                        'name' => '124 Spider',
                        'generations' => [[
                            'name' => 'Mk1 (348)',
                            'yearFrom' => 2016,
                            'yearTo' => 2020,
                        ]],
                    ]],
                ],
            ]),
            'https://raw.githubusercontent.com/lifeofcapo/car-api/abc123/car-parts.json' => Http::response([
                ['slug' => 'bumper-absorber', 'name' => 'Bumper absorber'],
            ]),
        ]);

        $source = new CatalogSource([
            'code' => 'LIFEOFCAPO',
            'settings' => [
                'repository_api_url' => 'https://api.github.com/repos/lifeofcapo/car-api',
                'raw_base_url' => 'https://raw.githubusercontent.com/lifeofcapo/car-api',
                'upstream_ref' => 'main',
            ],
        ]);
        $connector = new LifeOfCapoCatalogSourceConnector;

        $release = $connector->release($source);
        $records = iterator_to_array($connector->records($source, 'catalog'));

        $this->assertSame('abc123', $release['release_key']);
        $this->assertCount(2, $records);
        $this->assertSame('vehicle_generation', $records[0]['record_type']);
        $this->assertSame('Abarth', $records[0]['brand']);
        $this->assertSame('124 Spider', $records[0]['model']);
        $this->assertSame(2016, $records[0]['yearFrom']);
        $this->assertSame('generic_part_taxonomy', $records[1]['record_type']);
        $this->assertSame('bumper-absorber', $records[1]['slug']);

        Http::assertSent(fn ($request) => $request->url() === 'https://raw.githubusercontent.com/lifeofcapo/car-api/abc123/car-brands.json');
    }

    public function test_it_can_import_only_vehicle_records(): void
    {
        Http::fake([
            'https://api.github.com/repos/lifeofcapo/car-api/commits/main' => Http::response(['sha' => 'abc123']),
            'https://raw.githubusercontent.com/lifeofcapo/car-api/abc123/car-brands.json' => Http::response([
                ['brand' => 'Jeep', 'models' => [['name' => 'Wrangler', 'generations' => [['name' => 'JL', 'yearFrom' => 2018, 'yearTo' => null]]]]],
            ]),
        ]);

        $source = new CatalogSource(['settings' => []]);
        $records = iterator_to_array((new LifeOfCapoCatalogSourceConnector)->records($source, 'vehicles'));

        $this->assertCount(1, $records);
        $this->assertSame('Jeep', $records[0]['brand']);
        $this->assertSame('Wrangler', $records[0]['model']);
        $this->assertSame('JL', $records[0]['generation']);
    }
}
