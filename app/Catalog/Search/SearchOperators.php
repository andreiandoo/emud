<?php

namespace App\Catalog\Search;

use Illuminate\Support\Facades\DB;

/**
 * PostgreSQL spells case-insensitive matching `ilike`; SQLite has no such operator and its
 * `like` is already case-insensitive for ASCII. Production is PostgreSQL, but a hard-coded
 * `ilike` makes the filters it appears in untestable anywhere else, which is how they came to
 * be covered only by whichever tests happened to avoid them.
 */
class SearchOperators
{
    public static function like(): string
    {
        return DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
    }
}
