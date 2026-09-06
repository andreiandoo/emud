<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_search_documents', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('entity_type', 64)->index();
            $table->unsignedBigInteger('entity_id');
            $table->text('title')->nullable();
            $table->text('subtitle')->nullable();
            $table->text('brand')->nullable();
            $table->text('category')->nullable();
            $table->longText('search_text')->default('');
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('indexed_at')->nullable()->index();
            $table->timestampsTz();
            $table->unique(['entity_type', 'entity_id']);
            $table->index(['entity_type', 'entity_id'], 'catalog_search_documents_entity_idx');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("CREATE INDEX catalog_search_documents_fts_idx ON catalog_search_documents USING GIN (to_tsvector('simple', search_text))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_search_documents');
    }
};
