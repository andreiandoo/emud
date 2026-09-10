<?php

namespace App\Workshops\Web;

use App\Workshops\Contracts\WebSearchProvider;

/** No search API configured: website discovery uses only what the sources themselves provide. */
class NullWebSearchProvider implements WebSearchProvider
{
    public function name(): string
    {
        return 'null';
    }

    public function isConfigured(): bool
    {
        return false;
    }

    public function search(string $query, int $count = 10): array
    {
        return [];
    }
}
