<?php

namespace App\Catalog\Vehicles\Vin;

use App\Models\CatalogSource;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class VpicStandaloneReleaseDiscovery
{
    /** @return array{release_key:string,version:string,filename:string,url:string,retrieved_at:Carbon} */
    public function discover(CatalogSource $source): array
    {
        $downloadsUrl = (string) ($source->settings['downloads_url'] ?? 'https://vpic.nhtsa.dot.gov/Downloads');
        $this->assertTrustedUrl($downloadsUrl);

        $html = $this->request($source)->get($downloadsUrl)->throw()->body();
        preg_match_all(
            '/href=["\']([^"\']*vPICList_lite_(\d{4}_\d{2})\.custom\.zip[^"\']*)["\']/i',
            $html,
            $matches,
            PREG_SET_ORDER,
        );

        if ($matches === []) {
            throw new RuntimeException('No PostgreSQL custom vPIC standalone release was found on the official downloads page.');
        }

        $releases = [];
        foreach ($matches as $match) {
            $version = $match[2];
            $url = $this->absoluteUrl($downloadsUrl, html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5));
            $this->assertTrustedUrl($url);
            $releases[$version] = [
                'release_key' => 'vpic:'.$version,
                'version' => $version,
                'filename' => "vPICList_lite_{$version}.custom.zip",
                'url' => $url,
                'retrieved_at' => now(),
            ];
        }

        krsort($releases, SORT_STRING);

        return array_values($releases)[0];
    }

    private function absoluteUrl(string $base, string $href): string
    {
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }

        $parts = parse_url($base);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? 'vpic.nhtsa.dot.gov';

        if (str_starts_with($href, '/')) {
            return "{$scheme}://{$host}{$href}";
        }

        $path = $parts['path'] ?? '/Downloads';
        $directory = rtrim(str_contains($path, '.') ? dirname($path) : $path, '/');

        return "{$scheme}://{$host}{$directory}/".ltrim($href, '/');
    }

    private function assertTrustedUrl(string $url): void
    {
        $parts = parse_url($url);
        if (($parts['scheme'] ?? null) !== 'https' || strtolower((string) ($parts['host'] ?? '')) !== 'vpic.nhtsa.dot.gov') {
            throw new RuntimeException('vPIC standalone downloads must come from https://vpic.nhtsa.dot.gov.');
        }
    }

    private function request(CatalogSource $source): PendingRequest
    {
        $settings = $source->settings ?? [];

        return Http::withHeaders([
            'User-Agent' => (string) ($settings['user_agent'] ?? 'eMUD-Automotive-Catalog/1.0'),
            'Accept' => 'text/html,application/xhtml+xml',
        ])->timeout((int) ($settings['download_page_timeout_seconds'] ?? 30))
            ->retry(3, 1000, throw: false);
    }
}
