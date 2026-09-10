<?php

namespace App\Workshops\Web;

/**
 * Reads the few pages of a workshop's own website that say who it is and what it does: the home
 * page, then the contact, about and services pages it links to. Same host only, a handful of
 * pages at most, never the whole site, and never another domain it links to.
 */
class WebsiteCrawler
{
    private const INTERESTING = '/contact|despre|about|servic|lucrari|prestari|oferta|program|echipa|atelier|4x4|off-?road/i';

    /** Tried only when the home page links to none of the pages above. */
    private const GUESSES = ['/contact', '/servicii', '/despre-noi'];

    public function __construct(private WebsiteFetcher $fetcher, private WebsiteExtractor $extractor) {}

    /** @return list<FetchedPage> */
    public function crawl(string $startUrl, ?int $workshopId = null): array
    {
        $host = DirectoryDomains::host($startUrl);

        if ($host === null) {
            return [];
        }

        $max = max(1, (int) config('workshops.web.max_pages_per_site'));
        $home = $this->fetcher->fetch($startUrl, $workshopId);

        if (! $home->ok) {
            return [];
        }

        $pages = [$home];
        $seen = [$this->key($home->url) => true, $this->key($startUrl) => true];
        $siteHost = DirectoryDomains::host($home->url) ?? $host;
        $queue = $this->interestingLinks($home, $siteHost);

        if ($queue === []) {
            $root = $this->root($home->url);
            $queue = array_map(fn (string $path): string => $root.$path, self::GUESSES);
        }

        foreach ($queue as $url) {
            if (count($pages) >= $max) {
                break;
            }

            if (isset($seen[$this->key($url)])) {
                continue;
            }

            $seen[$this->key($url)] = true;
            $page = $this->fetcher->fetch($url, $workshopId);

            if ($page->ok && DirectoryDomains::host($page->url) === $siteHost) {
                $pages[] = $page;
            }
        }

        return $pages;
    }

    /** @return list<string> */
    private function interestingLinks(FetchedPage $page, string $siteHost): array
    {
        $links = [];

        foreach ($this->extractor->links($page->html, $page->url) as [$url, $text]) {
            if (DirectoryDomains::host($url) !== $siteHost || preg_match('/\.(pdf|jpe?g|png|gif|webp|zip|docx?|xlsx?)(\?|$)/i', $url) === 1) {
                continue;
            }

            if (preg_match(self::INTERESTING, $url.' '.$text) === 1) {
                $links[$this->key($url)] = $url;
            }
        }

        return array_values($links);
    }

    private function root(string $url): string
    {
        $parts = parse_url($url);

        return ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');
    }

    private function key(string $url): string
    {
        return rtrim(strtolower(preg_replace('/#.*$/', '', $url) ?? $url), '/');
    }
}
