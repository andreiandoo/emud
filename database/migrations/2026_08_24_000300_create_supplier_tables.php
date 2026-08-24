<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('protocol', 24);
            $table->string('connector_class')->nullable();
            $table->text('catalog_endpoint')->nullable();
            $table->text('stock_endpoint')->nullable();
            $table->text('price_endpoint')->nullable();
            $table->jsonb('credentials')->nullable();
            $table->jsonb('field_mapping')->nullable();
            $table->jsonb('settings')->nullable();
            $table->string('default_currency', 3)->default('RON');
            $table->string('timezone')->default('Europe/Bucharest');
            $table->unsignedSmallInteger('priority')->default(100);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_successful_sync_at')->nullable();
            $table->timestamps();
        });

        Schema::create('supplier_sync_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('mode', 24)->index();
            $table->string('status', 24)->default('pending')->index();
            $table->unsignedBigInteger('processed')->default(0);
            $table->unsignedBigInteger('created_count')->default(0);
            $table->unsignedBigInteger('updated_count')->default(0);
            $table->unsignedBigInteger('skipped_count')->default(0);
            $table->unsignedBigInteger('failed_count')->default(0);
            $table->jsonb('checkpoint')->nullable();
            $table->jsonb('summary')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['supplier_id', 'created_at']);
        });

        Schema::create('supplier_products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('external_id');
            $table->string('supplier_sku')->nullable()->index();
            $table->string('ean')->nullable()->index();
            $table->string('name');
            $table->text('source_url')->nullable();
            $table->string('source_hash', 64)->nullable();
            $table->jsonb('raw_payload')->nullable();
            $table->string('mapping_status', 24)->default('unmapped')->index();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamp('discontinued_at')->nullable();
            $table->timestamps();
            $table->unique(['supplier_id', 'external_id']);
        });

        Schema::create('supplier_offers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_product_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('cost_price', 14, 4)->nullable();
            $table->decimal('recommended_retail_price', 14, 2)->nullable();
            $table->string('currency', 3)->default('RON');
            $table->decimal('vat_rate', 5, 2)->nullable();
            $table->integer('stock_quantity')->nullable();
            $table->string('stock_status', 24)->default('unknown')->index();
            $table->unsignedSmallInteger('lead_time_days')->nullable();
            $table->unsignedInteger('minimum_order_quantity')->default(1);
            $table->timestamp('price_synced_at')->nullable();
            $table->timestamp('stock_synced_at')->nullable();
            $table->timestamp('stale_after')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('supplier_offer_history', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('supplier_offer_id')->constrained()->cascadeOnDelete();
            $table->decimal('cost_price', 14, 4)->nullable();
            $table->decimal('recommended_retail_price', 14, 2)->nullable();
            $table->integer('stock_quantity')->nullable();
            $table->string('stock_status', 24);
            $table->timestamp('recorded_at')->useCurrent()->index();
            $table->index(['supplier_offer_id', 'recorded_at']);
        });

        Schema::create('supplier_category_mappings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('external_category_id');
            $table->string('external_category_name')->nullable();
            $table->timestamps();
            $table->unique(['supplier_id', 'external_category_id']);
        });

        Schema::create('supplier_attribute_mappings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->string('external_field');
            $table->jsonb('transform')->nullable();
            $table->timestamps();
            $table->unique(['supplier_id', 'external_field']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX supplier_products_payload_idx ON supplier_products USING gin (raw_payload)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_attribute_mappings');
        Schema::dropIfExists('supplier_category_mappings');
        Schema::dropIfExists('supplier_offer_history');
        Schema::dropIfExists('supplier_offers');
        Schema::dropIfExists('supplier_products');
        Schema::dropIfExists('supplier_sync_runs');
        Schema::dropIfExists('suppliers');
    }
};
