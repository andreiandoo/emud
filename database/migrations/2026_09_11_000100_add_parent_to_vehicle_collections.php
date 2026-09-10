<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Collections get a parent.
 *
 * The hierarchy was already there implicitly — a collection naming only a make is broader than
 * one naming a model under it — but implicit is not addressable. Nothing could ask for "the top
 * level", which is the one question the public listing needs answered: 11.764 tiles is not a
 * page, 863 is.
 *
 * Exactly two levels, enforced in the editor rather than here: a parent must itself be a root.
 * That is what the shop needs — a make, and the derivatives under it — and it rules out cycles
 * by construction instead of by a check somebody has to remember to write.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Column and constraint separately, and no constraint on SQLite: a foreign key inside an
        // ALTER makes Laravel rebuild the whole table there, and a rebuild is both pointless for
        // eleven thousand rows and the thing that silently dropped a partial index last time.
        Schema::table('vehicle_collections', function (Blueprint $table): void {
            $table->unsignedBigInteger('parent_id')->nullable()->after('id');
            $table->index(['parent_id', 'position'], 'vehicle_collections_parent_position_index');
        });

        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('vehicle_collections', function (Blueprint $table): void {
                $table->foreign('parent_id')->references('id')->on('vehicle_collections')->nullOnDelete();
            });
        }

        // Backfilled in two passes, narrowest last, so a generation-level collection ends up
        // under its model rather than jumping straight to the make.
        $this->adopt(
            'model_id is not null and generation_id is null',
            'p.make_id = vehicle_collections.make_id and p.model_id is null and p.generation_id is null',
        );

        $this->adopt(
            'generation_id is not null',
            'p.model_id = vehicle_collections.model_id and p.generation_id is null',
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('vehicle_collections', function (Blueprint $table): void {
                $table->dropForeign(['parent_id']);
            });
        }

        Schema::table('vehicle_collections', function (Blueprint $table): void {
            $table->dropIndex('vehicle_collections_parent_position_index');
            $table->dropColumn('parent_id');
        });
    }

    /**
     * A correlated UPDATE rather than a loop: eleven thousand rows one at a time is eleven
     * thousand round trips, and both PostgreSQL and SQLite accept the target table by name
     * inside the subquery.
     */
    private function adopt(string $scope, string $match): void
    {
        DB::statement(<<<SQL
            update vehicle_collections
            set parent_id = (
                select p.id from vehicle_collections p
                where {$match} and p.id <> vehicle_collections.id
                limit 1
            )
            where parent_id is null and make_id is not null and ({$scope})
        SQL);
    }
};
