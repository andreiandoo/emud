<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_products', function (Blueprint $table): void {
            $table->string('manufacturer_part_number')->nullable()->after('ean')->index();
            $table->string('raw_brand')->nullable()->after('manufacturer_part_number')->index();
            $table->string('catalog_mapping_status', 24)->default('unmapped')->after('mapping_confidence')->index();
            $table->jsonb('catalog_mapping_reason')->nullable()->after('catalog_mapping_status');
            $table->timestampTz('catalog_mapped_at')->nullable()->after('catalog_mapping_reason');
        });

        Schema::create('supplier_product_match_candidates', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('supplier_product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('catalog_part_id')->constrained()->cascadeOnDelete();
            $table->decimal('score', 5, 2)->index();
            $table->jsonb('reasons')->nullable();
            $table->string('status', 24)->default('candidate')->index();
            $table->timestampsTz();
            $table->unique(['supplier_product_id', 'catalog_part_id']);
            $table->index(['supplier_product_id', 'score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_product_match_candidates');

        Schema::table('supplier_products', function (Blueprint $table): void {
            $table->dropColumn(['manufacturer_part_number', 'raw_brand', 'catalog_mapping_status', 'catalog_mapping_reason', 'catalog_mapped_at']);
        });
    }
};
