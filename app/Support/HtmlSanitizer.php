<?php

namespace App\Support;

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Renders author-supplied HTML with an allowlist.
 *
 * Editorial content is written by staff, not the public, but an allowlist still matters: a
 * compromised or careless admin account should not be able to put script into a page every
 * customer loads, and content imported from a supplier feed is not authored by us at all.
 *
 * Anything outside the allowlist is unwrapped rather than deleted, so stripping a tag never
 * silently removes the sentence inside it.
 */
class HtmlSanitizer
{
    /** @var array<string, list<string>> tag => allowed attributes */
    private const ALLOWED = [
        'p' => [], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [],
        'ul' => [], 'ol' => [], 'li' => [], 'blockquote' => [], 'hr' => [],
        'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => [],
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'title', 'width', 'height', 'loading'],
        'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => [], 'td' => [],
        'span' => [], 'div' => [], 'small' => [], 'sup' => [], 'sub' => [], 'code' => [], 'pre' => [],
    ];

    /** Dropped whole, contents included: their text is markup or script, not prose. */
    private const DISCARDED = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'link', 'meta'];

    private const SAFE_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    public function clean(?string $html): string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return '';
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        // The meta charset keeps DOMDocument from reading UTF-8 as Latin-1; the wrapper stops it
        // adding <html><body> of its own that would then be serialised back out.
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="emud-root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('emud-root');

        if ($root === null) {
            return '';
        }

        $this->cleanChildren($root);

        $output = '';

        foreach ($root->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        return trim($output);
    }

    private function cleanChildren(DOMNode $node): void
    {
        // Snapshotted because the loop rewrites the child list as it goes.
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                $this->cleanElement($child);
            }
        }
    }

    private function cleanElement(DOMElement $element): void
    {
        $tag = strtolower($element->tagName);

        if (in_array($tag, self::DISCARDED, true)) {
            $element->parentNode?->removeChild($element);

            return;
        }

        $this->cleanChildren($element);

        if (! array_key_exists($tag, self::ALLOWED)) {
            $this->unwrap($element);

            return;
        }

        foreach (iterator_to_array($element->attributes) as $attribute) {
            if ($attribute instanceof DOMAttr) {
                $this->cleanAttribute($element, $attribute, $tag);
            }
        }

        // A link that opens a new tab without noopener hands the opener to the target page.
        if ($tag === 'a' && $element->getAttribute('target') !== '') {
            $element->setAttribute('rel', 'noopener noreferrer');
        }
    }

    private function cleanAttribute(DOMElement $element, DOMAttr $attribute, string $tag): void
    {
        $name = strtolower($attribute->name);

        if (! in_array($name, self::ALLOWED[$tag], true)) {
            $element->removeAttribute($attribute->name);

            return;
        }

        if (in_array($name, ['href', 'src'], true) && ! $this->isSafeUrl($attribute->value)) {
            $element->removeAttribute($attribute->name);
        }
    }

    /**
     * Relative and anchor links stay; anything with a scheme has to name one that cannot execute,
     * which rules out javascript: and data: payloads.
     */
    private function isSafeUrl(string $url): bool
    {
        $url = trim($url);

        if ($url === '') {
            return false;
        }

        if (str_starts_with($url, '/') || str_starts_with($url, '#')) {
            return true;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        return $scheme === null
            ? ! str_contains($url, ':')
            : in_array(strtolower($scheme), self::SAFE_SCHEMES, true);
    }

    private function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;

        if ($parent === null) {
            return;
        }

        while ($element->firstChild !== null) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }
}
