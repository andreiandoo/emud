<?php

namespace App\Workshops\Web;

use App\Workshops\Data\PhoneNumber;
use App\Workshops\Support\PhoneNormalizer;
use App\Workshops\Support\TextNormalizer;
use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Pulls contacts and a description out of one page: mailto and tel links, WhatsApp links, social
 * profiles, schema.org JSON-LD, and numbers and addresses written in the text.
 *
 * Email addresses are kept only when they are the business's: a generic box (office@, contact@,
 * service@…), an address on the site's own domain, or one the page publishes as a mailto link or
 * in its structured data. A person's address that merely appears on the page is not collected.
 */
class WebsiteExtractor
{
    private const GENERIC_MAILBOXES = [
        'contact', 'office', 'service', 'info', 'programari', 'programare', 'receptie', 'vanzari', 'comenzi',
        'secretariat', 'piese', 'service-auto', 'serviceauto', 'admin', 'hello', 'salut', 'atelier', 'client',
        'clienti', 'suport', 'support', 'sales', 'booking', 'itp', 'tractari', 'daune',
    ];

    /**
     * @return array{
     *     emails: array<string, array{confidence: int, how: string}>,
     *     phones: list<PhoneNumber>,
     *     whatsapp: list<PhoneNumber>,
     *     facebook: list<string>,
     *     instagram: list<string>,
     *     names: list<string>,
     *     addresses: list<string>,
     *     geo: array{lat: float, lng: float}|null,
     *     title: string|null,
     *     text: string
     * }
     */
    public function extract(string $html, string $pageUrl, string $siteHost): array
    {
        $facts = ['emails' => [], 'phones' => [], 'whatsapp' => [], 'facebook' => [], 'instagram' => [], 'names' => [], 'addresses' => [], 'geo' => null, 'title' => null, 'text' => ''];

        if (trim($html) === '') {
            return $facts;
        }

        [$document, $xpath] = $this->load($html);
        $phones = [];

        foreach ($xpath->query('//a[@href]') ?: [] as $anchor) {
            $href = trim(html_entity_decode((string) $anchor->getAttribute('href')));

            if (preg_match('/^mailto:([^?]+)/i', $href, $match) === 1) {
                $this->email($facts, rawurldecode($match[1]), $siteHost, true);
            } elseif (preg_match('/^tel:(.+)$/i', $href, $match) === 1) {
                array_push($phones, ...PhoneNormalizer::extractAll(rawurldecode($match[1])));
            } elseif (preg_match('#(?:wa\.me/|api\.whatsapp\.com/send/?\?(?:.*&)?phone=)\+?(\d{8,15})#i', $href, $match) === 1) {
                if (($number = PhoneNormalizer::normalize('+'.$match[1])) !== null) {
                    $facts['whatsapp'][$number->e164] = $number;
                }
            } elseif (preg_match('#^https?://(?:[a-z]+\.)?facebook\.com/(?!sharer|share\.php|dialog|plugins|tr\b)[^\s"\']+#i', $href) === 1) {
                $facts['facebook'][] = strtok($href, '?');
            } elseif (preg_match('#^https?://(?:www\.)?instagram\.com/(?!p/|explore/)[^\s"\']+#i', $href) === 1) {
                $facts['instagram'][] = strtok($href, '?');
            }
        }

        foreach ($xpath->query('//script[@type="application/ld+json"]') ?: [] as $script) {
            $this->jsonLd($facts, json_decode(trim($script->textContent), true), $siteHost, $phones);
        }

        foreach ($xpath->query('//script|//style|//noscript|//svg') ?: [] as $node) {
            $node->parentNode?->removeChild($node);
        }

        $title = $xpath->query('//title')?->item(0)?->textContent;
        $facts['title'] = TextNormalizer::clean($title);
        $text = (string) TextNormalizer::clean($document->documentElement?->textContent ?? '');
        $facts['text'] = mb_substr($text, 0, 40_000);

        // Addresses written out as "office [at] service.ro" are common and meant to be read.
        $readable = preg_replace('/\s*(?:\[at]|\(at\)|\{at}| at )\s*/i', '@', $text) ?? $text;
        preg_match_all('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i', $readable, $emails);

        foreach ($emails[0] as $email) {
            $this->email($facts, $email, $siteHost, false);
        }

        preg_match_all('/(?:\+40|0040|\b0)\s*[237](?:[\s.\-\/]*\d){8}\b/', $text, $numbers);
        array_push($phones, ...PhoneNormalizer::extractAll(implode(';', $numbers[0])));

        $unique = [];

        foreach ($phones as $phone) {
            $unique[$phone->e164] ??= $phone;
        }

        $facts['phones'] = array_values($unique);
        $facts['whatsapp'] = array_values($facts['whatsapp']);
        $facts['facebook'] = array_values(array_unique($facts['facebook']));
        $facts['instagram'] = array_values(array_unique($facts['instagram']));

        return $facts;
    }

    /** @return list<array{0: string, 1: string}> absolute URL and link text */
    public function links(string $html, string $baseUrl): array
    {
        if (trim($html) === '') {
            return [];
        }

        [, $xpath] = $this->load($html);
        $links = [];

        foreach ($xpath->query('//a[@href]') ?: [] as $anchor) {
            $absolute = $this->absolute(trim((string) $anchor->getAttribute('href')), $baseUrl);

            if ($absolute !== null) {
                $links[] = [$absolute, (string) TextNormalizer::clean($anchor->textContent)];
            }
        }

        return $links;
    }

    private function email(array &$facts, string $email, string $siteHost, bool $published): void
    {
        $email = mb_strtolower(trim($email, " \t\n\r\0\x0B.,;:<>()[]"));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || preg_match('/\.(png|jpe?g|gif|webp|svg)$/i', $email) === 1) {
            return;
        }

        [$local, $domain] = explode('@', $email, 2);
        $ownDomain = $domain === $siteHost || str_ends_with($siteHost, '.'.$domain) || str_ends_with($domain, '.'.$siteHost);
        $generic = in_array(preg_replace('/[0-9]+$/', '', $local), self::GENERIC_MAILBOXES, true);

        [$confidence, $how] = match (true) {
            $generic && $ownDomain => [85, 'generic mailbox on the site domain'],
            $generic => [75, 'generic mailbox'],
            $ownDomain && $published => [70, 'published on the site domain'],
            $published && DirectoryDomains::isFreeMail($domain) => [60, 'published contact at a mail provider'],
            default => [0, ''],
        };

        if ($confidence > ($facts['emails'][$email]['confidence'] ?? 0)) {
            $facts['emails'][$email] = ['confidence' => $confidence, 'how' => $how];
        }
    }

    /** @param list<PhoneNumber> $phones */
    private function jsonLd(array &$facts, mixed $data, string $siteHost, array &$phones): void
    {
        if (! is_array($data)) {
            return;
        }

        if (array_is_list($data) || isset($data['@graph'])) {
            foreach ((array) ($data['@graph'] ?? $data) as $item) {
                $this->jsonLd($facts, $item, $siteHost, $phones);
            }

            return;
        }

        $types = array_map('strval', (array) ($data['@type'] ?? []));

        if (array_intersect($types, ['LocalBusiness', 'AutoRepair', 'AutomotiveBusiness', 'AutoPartsStore', 'AutoDealer', 'Organization', 'TireShop']) === []) {
            return;
        }

        if (is_string($data['name'] ?? null)) {
            $facts['names'][] = (string) TextNormalizer::clean($data['name']);
        }

        if (is_string($data['telephone'] ?? null)) {
            array_push($phones, ...PhoneNormalizer::extractAll($data['telephone']));
        }

        if (is_string($data['email'] ?? null)) {
            $this->email($facts, preg_replace('/^mailto:/i', '', $data['email']) ?? '', $siteHost, true);
        }

        $address = $data['address'] ?? null;

        if (is_array($address)) {
            $facts['addresses'][] = (string) TextNormalizer::clean(implode(', ', array_filter([
                $address['streetAddress'] ?? null, $address['addressLocality'] ?? null, $address['addressRegion'] ?? null,
            ], 'is_string')));
        } elseif (is_string($address)) {
            $facts['addresses'][] = (string) TextNormalizer::clean($address);
        }

        if (is_numeric($data['geo']['latitude'] ?? null) && is_numeric($data['geo']['longitude'] ?? null)) {
            $facts['geo'] = ['lat' => (float) $data['geo']['latitude'], 'lng' => (float) $data['geo']['longitude']];
        }

        foreach ((array) ($data['sameAs'] ?? []) as $url) {
            if (is_string($url) && str_contains($url, 'facebook.com/')) {
                $facts['facebook'][] = $url;
            } elseif (is_string($url) && str_contains($url, 'instagram.com/')) {
                $facts['instagram'][] = $url;
            }
        }
    }

    /** @return array{0: DOMDocument, 1: DOMXPath} */
    private function load(string $html): array
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOWARNING | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return [$document, new DOMXPath($document)];
    }

    private function absolute(string $href, string $base): ?string
    {
        if ($href === '' || str_starts_with($href, '#') || preg_match('/^(mailto|tel|javascript|data):/i', $href) === 1) {
            return null;
        }

        if (preg_match('#^https?://#i', $href) === 1) {
            return $href;
        }

        $parts = parse_url($base);

        if (! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $origin = $parts['scheme'].'://'.$parts['host'];

        if (str_starts_with($href, '//')) {
            return $parts['scheme'].':'.$href;
        }

        if (str_starts_with($href, '/')) {
            return $origin.$href;
        }

        $directory = preg_replace('#/[^/]*$#', '/', $parts['path'] ?? '/') ?? '/';

        return $origin.$directory.$href;
    }

    /** @internal for tests */
    public static function isElement(mixed $node): bool
    {
        return $node instanceof DOMElement;
    }
}
