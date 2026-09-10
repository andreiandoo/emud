<?php

namespace App\Workshops\Web;

use App\Workshops\Contracts\WebSearchProvider;
use App\Workshops\Ingestion\SourceHttpClient;
use App\Workshops\Ingestion\SourceThrottle;

/** The Brave Search API (https://api.search.brave.com), with a subscription key. */
class BraveWebSearchProvider implements WebSearchProvider
{
    public function __construct(private SourceHttpClient $http, private SourceThrottle $throttle) {}

    public function name(): string
    {
        return 'brave';
    }

    public function isConfigured(): bool
    {
        return filled(config('workshops.web.brave_api_key'));
    }

    public function search(string $query, int $count = 10): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        $this->throttle->wait('web-search', (int) config('workshops.web.search_delay_ms'));

        $json = $this->http->request(20, 2, 2000)
            ->acceptJson()
            ->withHeaders(['X-Subscription-Token' => (string) config('workshops.web.brave_api_key')])
            ->get((string) config('workshops.web.brave_endpoint'), [
                'q' => $query,
                'count' => max(1, min(20, $count)),
                'country' => 'RO',
                'search_lang' => 'ro',
                'safesearch' => 'strict',
            ])
            ->throw()
            ->json();

        return array_values(array_filter(array_map(fn (mixed $result): ?array => is_array($result) && isset($result['url']) ? [
            'url' => (string) $result['url'],
            'title' => isset($result['title']) ? strip_tags((string) $result['title']) : null,
            'snippet' => isset($result['description']) ? strip_tags((string) $result['description']) : null,
        ] : null, (array) ($json['web']['results'] ?? []))));
    }
}
