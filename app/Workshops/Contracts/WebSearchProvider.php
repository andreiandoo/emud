<?php

namespace App\Workshops\Contracts;

/**
 * A web search API with explicit terms of use. Scraping Google's result pages or Google Maps is
 * not an implementation of this, and never will be.
 */
interface WebSearchProvider
{
    public function name(): string;

    public function isConfigured(): bool;

    /** @return list<array{url: string, title: string|null, snippet: string|null}> */
    public function search(string $query, int $count = 10): array;
}
