<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether a signed-in customer wants listings and search narrowed to their car.
 *
 * One setting for the whole shop, kept on the account so that unticking it once is remembered on
 * the next page and on the next visit, instead of being asked for again on every listing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('filters_parts_by_vehicle')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('filters_parts_by_vehicle');
        });
    }
};
