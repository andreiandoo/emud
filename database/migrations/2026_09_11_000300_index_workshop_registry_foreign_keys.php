<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the foreign keys the registry looks up by. PostgreSQL does not index a foreign key
 * on its own, and the first national import showed what that costs: every workshop's
 * authorisations and every company's workshops were found by scanning the whole table, which
 * made a 17 000-record import take the better part of an hour.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workshops', function (Blueprint $table): void {
            $table->index('company_id');
            $table->index('merged_into_id');
        });

        Schema::table('workshop_authorizations', function (Blueprint $table): void {
            $table->index(['workshop_id', 'is_current']);
            $table->index('company_id');
        });

        Schema::table('workshop_match_candidates', function (Blueprint $table): void {
            $table->index('workshop_b_id');
        });

        Schema::table('workshop_source_records', function (Blueprint $table): void {
            $table->index('import_run_id');
        });
    }

    public function down(): void
    {
        Schema::table('workshop_source_records', function (Blueprint $table): void {
            $table->dropIndex(['import_run_id']);
        });

        Schema::table('workshop_match_candidates', function (Blueprint $table): void {
            $table->dropIndex(['workshop_b_id']);
        });

        Schema::table('workshop_authorizations', function (Blueprint $table): void {
            $table->dropIndex(['workshop_id', 'is_current']);
            $table->dropIndex(['company_id']);
        });

        Schema::table('workshops', function (Blueprint $table): void {
            $table->dropIndex(['company_id']);
            $table->dropIndex(['merged_into_id']);
        });
    }
};
