<?php

use App\Models\CatalogSource;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `catalog_change_events.api_redistributable` is a snapshot of the source's rights at the moment
 * the event was written, and the row kept only the source's *code*, inside the JSON payload.
 * That left the change feed unable to answer a revocation: an operator clearing a source's
 * redistribution rights stopped new events being published but left every existing one in the
 * feed. Linking the event to its source lets the feed check the live flag instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_change_events', function (Blueprint $table): void {
            $table->foreignId('catalog_source_id')->nullable()->constrained()->nullOnDelete();
            $table->index(['catalog_source_id', 'occurred_at']);
        });

        // Backfill from the code the payload already carried, one source at a time: a few
        // indexed updates rather than a row-by-row JSON parse.
        foreach (CatalogSource::query()->pluck('id', 'code') as $code => $id) {
            DB::table('catalog_change_events')
                ->whereNull('catalog_source_id')
                ->whereRaw(self::payloadSourceMatches(), [$code])
                ->update(['catalog_source_id' => $id]);
        }
    }

    public function down(): void
    {
        Schema::table('catalog_change_events', function (Blueprint $table): void {
            $table->dropIndex(['catalog_source_id', 'occurred_at']);
            $table->dropConstrainedForeignId('catalog_source_id');
        });
    }

    private static function payloadSourceMatches(): string
    {
        return DB::getDriverName() === 'pgsql'
            ? "payload->>'source' = ?"
            : "json_extract(payload, '$.source') = ?";
    }
};
