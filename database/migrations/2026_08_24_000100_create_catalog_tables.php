<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('website')->nullable();
            $table->text('description')->nullable();
            $table->string('logo_path')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('full_path')->unique();
            $table->unsignedSmallInteger('depth')->default(0)->index();
            $table->unsignedInteger('position')->default(0);
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->string('icon')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_visible_in_menu')->default(true);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->unique(['parent_id', 'slug']);
            $table->index(['parent_id', 'position']);
        });

        Schema::create('attributes', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('type', 24);
            $table->string('unit', 32)->nullable();
            $table->text('help_text')->nullable();
            $table->jsonb('validation_rules')->nullable();
            $table->boolean('is_global')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('attribute_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('value');
            $table->unsignedInteger('position')->default(0);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->unique(['attribute_id', 'value']);
        });

        Schema::create('attribute_category', function (Blueprint $table): void {
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_filterable')->default(true);
            $table->boolean('is_comparable')->default(true);
            $table->boolean('is_variant_defining')->default(false);
            $table->primary(['attribute_id', 'category_id']);
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('sku')->nullable()->unique();
            $table->string('manufacturer_part_number')->nullable()->index();
            $table->string('status', 24)->default('draft')->index();
            $table->string('product_type', 24)->default('physical');
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();
            $table->boolean('is_universal')->default(false)->index();
            $table->boolean('is_featured')->default(false)->index();
            $table->unsignedInteger('warranty_months')->nullable();
            $table->decimal('weight_kg', 10, 3)->nullable();
            $table->jsonb('dimensions_cm')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('category_product', function (Blueprint $table): void {
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->primary(['category_id', 'product_id']);
        });

        Schema::create('product_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('sku')->unique();
            $table->string('barcode')->nullable()->index();
            $table->string('manufacturer_part_number')->nullable()->index();
            $table->decimal('retail_price', 14, 2)->nullable();
            $table->decimal('compare_at_price', 14, 2)->nullable();
            $table->string('currency', 3)->default('RON');
            $table->decimal('weight_kg', 10, 3)->nullable();
            $table->jsonb('dimensions_cm')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('product_attribute_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->foreignId('option_id')->nullable()->constrained('attribute_options')->nullOnDelete();
            $table->text('value_text')->nullable();
            $table->decimal('value_number', 18, 6)->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->jsonb('value_json')->nullable();
            $table->timestamps();
            $table->index(['attribute_id', 'option_id']);
            $table->index(['attribute_id', 'value_number']);
            $table->index(['product_id', 'variant_id', 'attribute_id']);
        });

        Schema::create('product_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->string('type', 16)->default('image');
            $table->string('disk')->default('public');
            $table->text('path');
            $table->text('source_url')->nullable();
            $table->string('alt_text')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->index(['product_id', 'position']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
            DB::statement('CREATE INDEX products_name_trgm_idx ON products USING gin (name gin_trgm_ops)');
            DB::statement('CREATE INDEX product_attribute_values_json_idx ON product_attribute_values USING gin (value_json)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_media');
        Schema::dropIfExists('product_attribute_values');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('category_product');
        Schema::dropIfExists('products');
        Schema::dropIfExists('attribute_category');
        Schema::dropIfExists('attribute_options');
        Schema::dropIfExists('attributes');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('brands');
    }
};
