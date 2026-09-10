<?php

namespace App\Workshops\Web;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Fetches and caches each site's robots.txt for the life of the process. Following RFC 9309, a
 * missing file (any 4xx) allows everything, and a server error or an unreachable site allows
 * nothing, since then we cannot know what the site asked for.
 */
class RobotsPolicy
{
    /** @var array<string, RobotsTxt> */
    private array $cache = [];

    public function allows(string $url): bool
    {
        $parts = parse_url($url);

        if (! isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        $origin = strtolower($parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : ''));
        $path = ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');

        return ($this->cache[$origin] ??= $this->load($origin))->allows($path, $this->agentToken());
    }

    private function load(string $origin): RobotsTxt
    {
        try {
            $response = Http::withUserAgent((string) config('workshops.user_agent'))->timeout(10)->connectTimeout(10)->get($origin.'/robots.txt');
        } catch (Throwable) {
            return RobotsTxt::disallowAll();
        }

        if ($response->successful()) {
            return RobotsTxt::parse(mb_substr($response->body(), 0, 500_000));
        }

        return $response->status() >= 500 ? RobotsTxt::disallowAll() : RobotsTxt::allowAll();
    }

    /** "eMUD-WorkshopRegistry/1.0 (+…)" is matched in robots.txt as "emud-workshopregistry". */
    private function agentToken(): string
    {
        return strtolower(explode('/', (string) config('workshops.user_agent'))[0]);
    }
}
