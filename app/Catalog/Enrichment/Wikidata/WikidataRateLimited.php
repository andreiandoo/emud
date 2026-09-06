<?php

namespace App\Catalog\Enrichment\Wikidata;

use RuntimeException;

class WikidataRateLimited extends RuntimeException
{
    public function __construct(public readonly int $retryAfterSeconds, string $message = 'Wikidata asked the client to slow down.')
    {
        parent::__construct($message);
    }
}
