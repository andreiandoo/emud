<?php

namespace App\Catalog\Enrichment\Wikidata;

use App\Models\CatalogSource;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WikidataClient
{
    /** @return array<int, array<string, mixed>> */
    public function search(CatalogSource $source, string $term, string $language = 'en'): array
    {
        $response = $this->get($source, [
            'action' => 'wbsearchentities',
            'search' => $term,
            'language' => $language,
            'uselang' => $language,
            'type' => 'item',
            'limit' => (int) ($source->settings['search_limit'] ?? 5),
            'format' => 'json',
            'maxlag' => (int) ($source->settings['maxlag'] ?? 5),
        ]);

        $results = data_get($response->json(), 'search', []);

        return is_array($results) ? array_values($results) : [];
    }

    /** @return array<string, mixed> */
    public function entity(CatalogSource $source, string $qid, array $languages = ['en', 'ro']): array
    {
        $response = $this->get($source, [
            'action' => 'wbgetentities',
            'ids' => $qid,
            'props' => 'labels|aliases|descriptions',
            'languages' => implode('|', array_values(array_unique($languages))),
            'languagefallback' => 1,
            'format' => 'json',
            'maxlag' => (int) ($source->settings['maxlag'] ?? 5),
        ]);

        $entity = data_get($response->json(), 'entities.'.$qid, []);

        return is_array($entity) ? $entity : [];
    }

    private function get(CatalogSource $source, array $query): Response
    {
        $endpoint = (string) ($source->settings['action_api_url'] ?? 'https://www.wikidata.org/w/api.php');
        $userAgent = trim((string) ($source->settings['user_agent'] ?? ''));
        if ($userAgent === '') {
            throw new RuntimeException('Wikidata source requires a descriptive user_agent with project/contact information.');
        }

        $response = Http::withHeaders(['User-Agent' => $userAgent])
            ->acceptJson()
            ->timeout((int) ($source->settings['timeout_seconds'] ?? 30))
            ->get($endpoint, $query);

        $apiError = (string) data_get($response->json(), 'error.code', '');
        if ($response->status() === 429 || $apiError === 'ratelimited' || $apiError === 'maxlag') {
            $fallback = $apiError === 'maxlag' ? 10 : 30;
            $retryAfter = max(1, (int) ($response->header('Retry-After') ?: $fallback));

            throw new WikidataRateLimited($retryAfter, "Wikidata throttled the request ({$apiError}).");
        }

        return $response->throw();
    }
}
