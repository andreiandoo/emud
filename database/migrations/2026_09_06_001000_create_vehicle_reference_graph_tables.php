<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_manufacturers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('country')->nullable()->index();
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->text('address')->nullable();
            $table->string('postal_code', 32)->nullable();
            $table->jsonb('manufacturer_types')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestampsTz();
        });

        Schema::create('vehicle_make_manufacturers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('make_id')->constrained('vehicle_makes')->cascadeOnDelete();
            $table->foreignId('manufacturer_id')->constrained('vehicle_manufacturers')->cascadeOnDelete();
            $table->foreignId('catalog_source_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('year_from')->nullable();
            $table->unsignedSmallInteger('year_to')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
            $table->unique(['make_id', 'manufacturer_id', 'catalog_source_id'], 'vehicle_make_mfr_source_unique');
        });

        Schema::create('vehicle_entity_identifiers', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('entity_type', 32)->index();
            $table->unsignedBigInteger('entity_id')->index();
            $table->string('scheme', 48)->index();
            $table->text('value_raw');
            $table->string('value_normalized', 255)->index();
            $table->foreignId('catalog_source_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('confidence', 5, 2)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
            $table->unique(['entity_type', 'scheme', 'value_normalized', 'catalog_source_id'], 'vehicle_entity_identifier_unique');
            $table->index(['scheme', 'value_normalized'], 'vehicle_entity_identifier_lookup');
        });

        Schema::create('vehicle_make_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('make_id')->constrained('vehicle_makes')->cascadeOnDelete();
            $table->string('type_code', 64)->nullable();
            $table->string('type_name');
            $table->foreignId('catalog_source_id')->nullable()->constrained()->nullOnDelete();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
            $table->unique(['make_id', 'type_name', 'catalog_source_id'], 'vehicle_make_type_source_unique');
        });

        Schema::create('vehicle_model_years', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('model_id')->constrained('vehicle_models')->cascadeOnDelete();
            $table->unsignedSmallInteger('model_year')->index();
            $table->string('vehicle_type')->nullable()->index();
            $table->foreignId('catalog_source_id')->nullable()->constrained()->nullOnDelete();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
            $table->unique(['model_id', 'model_year', 'vehicle_type', 'catalog_source_id'], 'vehicle_model_year_source_unique');
        });

        Schema::create('vehicle_wmis', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('manufacturer_id')->constrained('vehicle_manufacturers')->cascadeOnDelete();
            $table->foreignId('make_id')->nullable()->constrained('vehicle_makes')->nullOnDelete();
            $table->string('wmi', 16)->index();
            $table->string('vehicle_type')->nullable()->index();
            $table->foreignId('catalog_source_id')->nullable()->constrained()->nullOnDelete();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
            $table->unique(['manufacturer_id', 'wmi', 'catalog_source_id'], 'vehicle_wmi_source_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_wmis');
        Schema::dropIfExists('vehicle_model_years');
        Schema::dropIfExists('vehicle_make_types');
        Schema::dropIfExists('vehicle_entity_identifiers');
        Schema::dropIfExists('vehicle_make_manufacturers');
        Schema::dropIfExists('vehicle_manufacturers');
    }
};
