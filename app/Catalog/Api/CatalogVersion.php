<?php

namespace App\Catalog\Api;

use Illuminate\Support\Facades\Cache;

/**
 * A stamp that changes whenever anything the public API can return changes.
 *
 * Cached responses are keyed by it, so an import, a re-canonicalization or an operator revoking
 * a source's redistribution rights retires every cached page at once. That matters more than the
 * cache TTL: publication rights must never be served stale, because the stale answer is the one
 * that republishes data the owner just withdrew.
 */
class CatalogVersion
{
    private const KEY = 'catalog:api:version';

    public static function current(): string
    {
        return (string) Cache::get(self::KEY, '0');
    }

    public static function bump(): void
    {
        // Last writer wins. Two concurrent bumps producing one stamp is harmless — either value
        // is a version nothing has been cached under yet.
        Cache::forever(self::KEY, (string) (int) (microtime(true) * 1000));
    }
}
