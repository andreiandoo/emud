<?php

namespace App\Workshops\Web;

use App\Models\Workshop;
use App\Models\WorkshopWebsiteCandidate;
use App\Workshops\Contracts\WebSearchProvider;
use App\Workshops\Support\CompanyNameNormalizer;
use App\Workshops\Support\TextNormalizer;
use App\Workshops\Support\TokenSimilarity;
use Throwable;

/**
 * Finds a workshop's own website and decides whether a candidate really is it.
 *
 * Candidates come first from what the sources already say (an OSM website tag, the WEB field of
 * the trade register, the domain of a business email address), then from a web search API when
 * one is configured. A candidate is accepted only when its pages carry the workshop's identity:
 * its phone number, its fiscal code, its name, its town or its street. Listing sites and social
 * networks are never accepted as the website.
 */
class WebsiteDiscoverer
{
    public const ACCEPT_AT = 60;

    public function __construct(
        private WebSearchProvider $search,
        private WebsiteFetcher $fetcher,
        private WebsiteExtractor $extractor,
    ) {}

    /** @return array{candidates: int, accepted: string|null, searched: bool} */
    public function discover(Workshop $workshop): array
    {
        $workshop->loadMissing(['contacts', 'company']);
        $this->fromKnownSources($workshop);
        $searched = false;

        if ($this->search->isConfigured() && ! $this->hasAccepted($workshop)) {
            $this->fromSearch($workshop);
            $searched = true;
        }

        // Listing and social sites are turned away without fetching anything.
        foreach ($workshop->websiteCandidates()->where('validation_status', WorkshopWebsiteCandidate::PENDING)->get() as $candidate) {
            if (DirectoryDomains::isDirectory($candidate->domain) || DirectoryDomains::isSocial($candidate->domain)) {
                $this->validate($workshop, $candidate);
            }
        }

        foreach ($workshop->websiteCandidates()->where('validation_status', WorkshopWebsiteCandidate::PENDING)->orderByDesc('confidence')->limit(5)->get() as $candidate) {
            $this->validate($workshop, $candidate);

            if ($candidate->validation_status === WorkshopWebsiteCandidate::ACCEPTED) {
                break;
            }
        }

        $accepted = $workshop->websiteCandidates()->where('validation_status', WorkshopWebsiteCandidate::ACCEPTED)->orderByDesc('confidence')->first();
        $workshop->forceFill(['website_status' => $accepted !== null ? 'found' : 'not_found', 'website_checked_at' => now()])->save();

        return ['candidates' => $workshop->websiteCandidates()->count(), 'accepted' => $accepted?->url, 'searched' => $searched];
    }

    public function validate(Workshop $workshop, WorkshopWebsiteCandidate $candidate): void
    {
        if (DirectoryDomains::isDirectory($candidate->domain) || DirectoryDomains::isSocial($candidate->domain)) {
            $candidate->update(['validation_status' => WorkshopWebsiteCandidate::REJECTED, 'validated_at' => now(), 'evidence' => array_merge((array) $candidate->evidence, ['rejected' => 'listing or social site'])]);

            return;
        }

        $page = $this->fetcher->fetch($candidate->url, $workshop->id);

        if (! $page->ok) {
            $candidate->update(['validation_status' => WorkshopWebsiteCandidate::UNREACHABLE, 'validated_at' => now(), 'evidence' => array_merge((array) $candidate->evidence, ['error' => $page->error])]);

            return;
        }

        $facts = $this->extractor->extract($page->html, $page->url, $candidate->domain);
        [$score, $signals] = $this->identity($workshop, $facts);
        $base = match ($candidate->discovered_via) {
            'osm', 'onrc' => 25,
            'email_domain' => 30,
            'manual' => 60,
            default => 0,
        };
        $confidence = min(100, $base + $score);

        $candidate->update([
            'url' => $page->url,
            'confidence' => $confidence,
            'validation_status' => $confidence >= self::ACCEPT_AT ? WorkshopWebsiteCandidate::ACCEPTED : WorkshopWebsiteCandidate::REJECTED,
            'validated_at' => now(),
            'evidence' => array_merge((array) $candidate->evidence, ['signals' => $signals, 'page_record_id' => $page->record?->id, 'title' => $facts['title']]),
        ]);
    }

    /** @return array{0: int, 1: array<string, mixed>} */
    private function identity(Workshop $workshop, array $facts): array
    {
        $score = 0;
        $signals = [];
        $digits = preg_replace('/\D+/', '', $facts['text']) ?? '';
        $known = $workshop->contacts->whereIn('type', ['phone', 'mobile'])->pluck('normalized_value')->all();
        $pagePhones = array_map(fn ($phone) => $phone->e164, $facts['phones']);

        if (array_intersect($known, $pagePhones) !== [] || collect($known)->contains(fn (string $e164): bool => str_contains($digits, '0'.substr($e164, 3)))) {
            $score += 45;
            $signals['phone'] = true;
        }

        if ($workshop->company?->cui !== null && preg_match('/\b(?:RO)?\s?'.preg_quote($workshop->company->cui, '/').'\b/i', $facts['text']) === 1) {
            $score += 45;
            $signals['cui'] = true;
        }

        $folded = TextNormalizer::fold($facts['text'].' '.$facts['title'].' '.implode(' ', $facts['names']));
        $name = TokenSimilarity::tokens(CompanyNameNormalizer::normalize($workshop->company?->legal_name ?? $workshop->name));
        $present = array_filter($name, fn (string $token): bool => strlen($token) >= 3 && str_contains(' '.$folded.' ', ' '.$token.' '));
        $share = $name === [] ? 0 : count($present) / count($name);

        if ($share >= 0.6) {
            $score += 30;
            $signals['name'] = round($share, 2);
        }

        if (($locality = TextNormalizer::fold($workshop->locality)) !== '' && str_contains($folded, $locality)) {
            $score += 10;
            $signals['locality'] = true;
        }

        $street = TextNormalizer::fold($workshop->street);
        $streetWords = array_filter(explode(' ', $street), fn (string $word): bool => strlen($word) >= 5 && ! in_array($word, ['strada', 'soseaua', 'bulevardul', 'calea'], true));

        if ($streetWords !== [] && collect($streetWords)->every(fn (string $word): bool => str_contains($folded, $word))) {
            $score += 15;
            $signals['street'] = true;
        }

        return [$score, $signals];
    }

    private function fromKnownSources(Workshop $workshop): void
    {
        foreach ($workshop->contacts->where('type', 'website') as $contact) {
            $this->candidate($workshop, $contact->value, 'osm', 70, ['contact_id' => $contact->id]);
        }

        if ($workshop->company?->website !== null) {
            $this->candidate($workshop, $workshop->company->website, 'onrc', 65, ['onrc_web' => $workshop->company->website]);
        }

        foreach ($workshop->contacts->where('type', 'email') as $contact) {
            $domain = substr((string) strrchr($contact->normalized_value, '@'), 1);

            if ($domain !== '' && ! DirectoryDomains::isFreeMail($domain)) {
                $this->candidate($workshop, 'https://'.$domain, 'email_domain', 55, ['email' => $contact->normalized_value]);
            }
        }
    }

    private function fromSearch(Workshop $workshop): void
    {
        $name = $workshop->company?->legal_name ?? $workshop->name;
        $queries = array_filter([
            trim($name.' '.$workshop->locality),
            ($phone = $workshop->primaryContact('phone', 'mobile')) !== null ? '"'.$phone->value.'"' : null,
        ]);

        foreach ($queries as $query) {
            try {
                $results = $this->search->search($query, 8);
            } catch (Throwable $exception) {
                report($exception);

                return;
            }

            foreach ($results as $result) {
                $host = DirectoryDomains::host($result['url']);

                if ($host !== null && ! DirectoryDomains::isDirectory($host) && ! DirectoryDomains::isSocial($host)) {
                    $this->candidate($workshop, $result['url'], 'search', 20, ['query' => $query, 'title' => $result['title'], 'snippet' => $result['snippet'], 'provider' => $this->search->name()]);
                }
            }
        }
    }

    private function candidate(Workshop $workshop, string $url, string $via, int $confidence, array $evidence): void
    {
        $host = DirectoryDomains::host($url);

        if ($host === null || DirectoryDomains::isDirectory($host) || DirectoryDomains::isSocial($host)) {
            return;
        }

        $candidate = WorkshopWebsiteCandidate::query()->firstOrNew(['workshop_id' => $workshop->id, 'domain' => $host]);

        if ($candidate->exists && $candidate->validation_status !== WorkshopWebsiteCandidate::PENDING) {
            return;
        }

        $candidate->fill([
            'url' => $candidate->url ?? $url,
            'discovered_via' => $candidate->discovered_via ?? $via,
            'confidence' => max((int) $candidate->confidence, $confidence),
            'evidence' => array_merge((array) $candidate->evidence, [$via => $evidence]),
            'validation_status' => WorkshopWebsiteCandidate::PENDING,
        ])->save();
    }

    private function hasAccepted(Workshop $workshop): bool
    {
        return $workshop->websiteCandidates()->where('validation_status', WorkshopWebsiteCandidate::ACCEPTED)->exists();
    }
}
