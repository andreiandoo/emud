<?php

namespace Tests\Unit\Catalog;

use App\Catalog\Vehicles\Vin\VpicStandaloneReleaseDiscovery;
use App\Models\CatalogSource;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VpicStandaloneReleaseDiscoveryTest extends TestCase
{
    public function test_it_discovers_the_latest_official_postgresql_custom_release(): void
    {
        Http::fake([
            'https://vpic.nhtsa.dot.gov/Downloads' => Http::response(<<<'HTML'
                <html><body>
                    <a href="/Downloads/vPICList_lite_2026_07.custom.zip">July custom</a>
                    <a href="vPICList_lite_2026_08.custom.zip">August custom</a>
                    <a href="vPICList_lite_2026_08.plain.zip">August plain</a>
                </body></html>
                HTML),
        ]);

        $source = new CatalogSource([
            'settings' => [
                'downloads_url' => 'https://vpic.nhtsa.dot.gov/Downloads',
                'user_agent' => 'eMUD-Test/1.0',
            ],
        ]);

        $release = (new VpicStandaloneReleaseDiscovery)->discover($source);

        $this->assertSame('vpic:2026_08', $release['release_key']);
        $this->assertSame('2026_08', $release['version']);
        $this->assertSame('vPICList_lite_2026_08.custom.zip', $release['filename']);
        $this->assertSame('https://vpic.nhtsa.dot.gov/Downloads/vPICList_lite_2026_08.custom.zip', $release['url']);
    }

    public function test_it_refuses_non_nhtsa_download_hosts(): void
    {
        Http::fake([
            'https://vpic.nhtsa.dot.gov/Downloads' => Http::response(
                '<a href="https://evil.example/vPICList_lite_2026_99.custom.zip">fake</a>',
            ),
        ]);

        $source = new CatalogSource([
            'settings' => ['downloads_url' => 'https://vpic.nhtsa.dot.gov/Downloads'],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('must come from https://vpic.nhtsa.dot.gov');

        (new VpicStandaloneReleaseDiscovery)->discover($source);
    }
}
