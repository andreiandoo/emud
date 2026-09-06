<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_sources', function (Blueprint $table): void {
            $table->foreignId('supplier_id')->nullable()->unique()->constrained()->nullOnDelete();
        });

        Schema::table('catalog_source_records', function (Blueprint $table): void {
            $table->foreignId('supplier_product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_sync_run_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_feed_artifact_id')->nullable()->constrained()->nullOnDelete();
            $table->index(['supplier_product_id', 'supplier_sync_run_id', 'supplier_feed_artifact_id'], 'catalog_source_records_supplier_provenance_idx');
        });

        Schema::table('supplier_products', function (Blueprint $table): void {
            $table->jsonb('technical_payload')->nullable();
            $table->foreignId('last_supplier_sync_run_id')->nullable()->constrained('supplier_sync_runs')->nullOnDelete();
            $table->string('technical_promotion_status', 32)->default('not_eligible')->index();
            $table->text('technical_promotion_error')->nullable();
            $table->timestampTz('technical_promoted_at')->nullable();
        });

        Schema::table('catalog_part_numbers', function (Blueprint $table): void {
            $table->foreignId('catalog_source_record_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::table('catalog_part_attributes', function (Blueprint $table): void {
            $table->foreignId('catalog_source_record_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::table('catalog_fitments', function (Blueprint $table): void {
            $table->foreignId('catalog_source_record_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::table('catalog_part_relations', function (Blueprint $table): void {
            $table->foreignId('catalog_source_record_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::create('catalog_unresolved_part_relations', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('source_part_id')->constrained('catalog_parts')->cascadeOnDelete();
            $table->string('relation_type', 48)->index();
            $table->string('target_scheme', 32)->default('MPN')->index();
            $table->text('target_brand_raw')->nullable();
            $table->string('target_brand_normalized', 255)->nullable()->index();
            $table->text('target_number_raw');
            $table->string('target_number_normalized', 255)->index();
            $table->string('target_number_compact', 255)->nullable()->index();
            $table->foreignId('catalog_source_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('catalog_source_record_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('confidence', 5, 2)->nullable();
            $table->string('status', 24)->default('pending')->index();
            $table->foreignId('resolved_target_part_id')->nullable()->constrained('catalog_parts')->nullOnDelete();
            $table->timestampTz('resolved_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
            $table->index(['status', 'target_number_compact'], 'catalog_unresolved_relations_lookup_idx');
            $table->index(['source_part_id', 'relation_type'], 'catalog_unresolved_relations_source_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_unresolved_part_relations');

        Schema::table('catalog_part_relations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('catalog_source_record_id');
        });

        Schema::table('catalog_fitments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('catalog_source_record_id');
        });

        Schema::table('catalog_part_attributes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('catalog_source_record_id');
        });

        Schema::table('catalog_part_numbers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('catalog_source_record_id');
        });

        Schema::table('supplier_products', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('last_supplier_sync_run_id');
            $table->dropColumn(['technical_payload', 'technical_promotion_status', 'technical_promotion_error', 'technical_promoted_at']);
        });

        Schema::table('catalog_source_records', function (Blueprint $table): void {
            $table->dropIndex('catalog_source_records_supplier_provenance_idx');
            $table->dropConstrainedForeignId('supplier_feed_artifact_id');
            $table->dropConstrainedForeignId('supplier_sync_run_id');
            $table->dropConstrainedForeignId('supplier_product_id');
        });

        Schema::table('catalog_sources', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('supplier_id');
        });
    }
};
