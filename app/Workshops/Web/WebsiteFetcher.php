<?php

namespace App\Workshops\Web;

use App\Models\WorkshopDataSource;
use App\Workshops\Data\SourceRecordData;
use App\Workshops\Ingestion\DataSourceCatalog;
use App\Workshops\Ingestion\SourceHttpClient;
use App\Workshops\Ingestion\SourceRecordStore;
use App\Workshops\Ingestion\SourceThrottle;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

/**
 * Fetches one page of a workshop's website, politely: robots.txt first, one request per host
 * every few seconds, a timeout, and a size cap. Every page fetched is kept as a source record, so
 * what a contact or a service was read from can be shown later.
 */
class WebsiteFetcher
{
    public function __construct(
        private SourceHttpClient $http,
        private SourceThrottle $throttle,
        private RobotsPolicy $robots,
        private SourceRecordStore $store,
    ) {}

    public function fetch(string $url, ?int $workshopId = null, bool $htmlOnly = true): FetchedPage
    {
        $host = DirectoryDomains::host($url);

        if ($host === null) {
            return FetchedPage::failed($url, 'invalid URL');
        }

        if (! $this->robots->allows($url)) {
            return FetchedPage::failed($url, 'robots.txt disallows it');
        }

        $maxBytes = (int) config('workshops.web.max_response_bytes');
        $this->throttle->wait('web:'.$host, (int) config('workshops.web.request_delay_ms'));

        try {
            $response = $this->http->request((int) config('workshops.web.request_timeout'), 1, 2000)
                ->withHeaders(['Accept' => $htmlOnly ? 'text/html,application/xhtml+xml' : '*/*', 'Accept-Language' => 'ro,en;q=0.5'])
                ->withOptions([
                    'allow_redirects' => ['max' => 5, 'track_redirects' => true],
                    'on_headers' => function (ResponseInterface $response) use ($maxBytes): void {
                        if ((int) $response->getHeaderLine('Content-Length') > $maxBytes) {
                            throw new RuntimeException('response larger than the configured limit');
                        }
                    },
                ])
                ->get($url);
        } catch (Throwable $exception) {
            return FetchedPage::failed($url, $exception->getMessage());
        }

        $redirects = $response->header('X-Guzzle-Redirect-History');
        $finalUrl = $redirects !== '' ? trim((string) last(explode(',', $redirects))) : $url;
        $type = strtolower($response->header('Content-Type'));

        if (! $response->successful()) {
            return FetchedPage::failed($finalUrl, 'HTTP '.$response->status(), $response->status());
        }

        if ($htmlOnly && $type !== '' && ! str_contains($type, 'html')) {
            return FetchedPage::failed($finalUrl, "not a web page ({$type})", $response->status());
        }

        $body = substr($response->body(), 0, $maxBytes);
        $body = mb_check_encoding($body, 'UTF-8') ? $body : mb_convert_encoding($body, 'UTF-8', 'Windows-1252');

        $record = $this->store->store(WorkshopDataSource::forKey(DataSourceCatalog::WEBSITE), new SourceRecordData(
            recordType: 'page',
            externalId: sha1($finalUrl),
            payload: ['url' => $finalUrl, 'requested_url' => $url, 'content_type' => $type, 'workshop_id' => $workshopId],
            rawContent: $body,
            sourceReference: $finalUrl,
            httpStatus: $response->status(),
        ))->record;

        $record->markParsed();

        return new FetchedPage($finalUrl, true, $response->status(), $body, $record);
    }
}
