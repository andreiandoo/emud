<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_part_relations', function (Blueprint $table): void {
            $table->index(['target_part_id', 'relation_type'], 'catalog_part_relations_target_type_idx');
            $table->index(['catalog_source_id', 'relation_type'], 'catalog_part_relations_source_type_idx');
        });
    }

    public function down(): void
    {
        Schema::table('catalog_part_relations', function (Blueprint $table): void {
            $table->dropIndex('catalog_part_relations_target_type_idx');
            $table->dropIndex('catalog_part_relations_source_type_idx');
        });
    }
};
