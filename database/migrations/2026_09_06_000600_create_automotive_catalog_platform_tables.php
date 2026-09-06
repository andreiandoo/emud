<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_sources', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('source_type', 48)->index();
            $table->string('protocol', 24)->default('http');
            $table->string('connector_class')->nullable();
            $table->text('base_url')->nullable();
            $table->text('catalog_endpoint')->nullable();
            $table->longText('credentials')->nullable();
            $table->jsonb('settings')->nullable();
            $table->jsonb('field_mapping')->nullable();
            $table->string('rights_class', 48)->default('unknown_pending_review')->index();
            $table->boolean('allow_internal')->default(true);
            $table->boolean('allow_ecommerce')->default(false);
            $table->boolean('allow_derived')->default(false);
            $table->boolean('allow_api_redistribution')->default(false);
            $table->boolean('allow_bulk_export')->default(false);
            $table->boolean('allow_media_redistribution')->default(false);
            $table->boolean('attribution_required')->default(false);
            $table->text('license_name')->nullable();
            $table->text('license_url')->nullable();
            $table->text('legal_notes')->nullable();
            $table->jsonb('territories')->nullable();
            $table->jsonb('capabilities')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestampTz('last_successful_sync_at')->nullable();
            $table->timestampTz('last_attempted_sync_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('catalog_source_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('catalog_source_id')->constrained()->cascadeOnDelete();
            $table->string('mode', 32)->default('catalog');
            $table->string('cron_expression', 100);
            $table->string('timezone')->default('Europe/Bucharest');
            $table->boolean('is_enabled')->default(true)->index();
            $table->timestampTz('last_dispatched_at')->nullable();
            $table->timestampsTz();
            $table->unique(['catalog_source_id', 'mode']);
        });

        Schema::create('catalog_source_releases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('catalog_source_id')->constrained()->cascadeOnDelete();
            $table->string('release_key');
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('retrieved_at')->nullable();
            $table->string('checksum_sha256', 64)->nullable();
            $table->text('raw_object_path')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
            $table->unique(['catalog_source_id', 'release_key']);
        });

        Schema::create('catalog_import_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('catalog_source_id')->constrained()->cascadeOnDelete();
            $table->foreignId('catalog_source_release_id')->nullable()->constrained()->nullOnDelete();
            $table->string('mode', 32)->default('catalog')->index();
            $table->string('status', 32)->default('pending')->index();
            $table->string('importer_name')->nullable();
            $table->string('importer_version')->nullable();
            $table->unsignedBigInteger('fetched_count')->default(0);
            $table->unsignedBigInteger('parsed_count')->default(0);
            $table->unsignedBigInteger('matched_count')->default(0);
            $table->unsignedBigInteger('published_count')->default(0);
            $table->unsignedBigInteger('skipped_count')->default(0);
            $table->unsignedBigInteger('failed_count')->default(0);
            $table->jsonb('checkpoint')->nullable();
            $table->jsonb('summary')->nullable();
            $table->text('error_message')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->timestampsTz();
            $table->index(['catalog_source_id', 'created_at']);
        });

        Schema::create('catalog_source_records', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('catalog_source_id')->constrained()->cascadeOnDelete();
            $table->foreignId('catalog_source_release_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('catalog_import_run_id')->nullable()->constrained()->nullOnDelete();
            $table->string('record_type', 48)->index();
            $table->string('external_id');
            $table->string('checksum_sha256', 64)->nullable()->index();
            $table->jsonb('raw_payload');
            $table->jsonb('normalized_payload')->nullable();
            $table->string('mapping_status', 32)->default('unprocessed')->index();
            $table->text('mapping_notes')->nullable();
            $table->boolean('deleted_at_source')->default(false)->index();
            $table->timestampTz('source_updated_at')->nullable();
            $table->timestampTz('last_seen_at')->nullable()->index();
            $table->timestampsTz();
            $table->unique(['catalog_source_id', 'record_type', 'external_id']);
        });

        Schema::create('catalog_source_assertions', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('catalog_source_id')->constrained()->cascadeOnDelete();
            $table->foreignId('catalog_source_record_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('catalog_import_run_id')->nullable()->constrained()->nullOnDelete();
            $table->string('entity_type', 64)->index();
            $table->unsignedBigInteger('entity_id')->nullable()->index();
            $table->string('field_or_relation', 128)->index();
            $table->jsonb('raw_value')->nullable();
            $table->jsonb('normalized_value')->nullable();
            $table->decimal('confidence', 5, 2)->nullable();
            $table->string('status', 32)->default('raw')->index();
            $table->boolean('ecommerce_displayable')->default(false);
            $table->boolean('api_redistributable')->default(false);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->timestampsTz();
            $table->index(['entity_type', 'entity_id', 'field_or_relation'], 'catalog_assertions_entity_field_idx');
        });

        Schema::create('catalog_mapping_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('catalog_source_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('entity_type', 48)->index();
            $table->string('field', 64)->nullable();
            $table->string('match_type', 24)->default('exact');
            $table->text('source_value');
            $table->jsonb('target')->nullable();
            $table->unsignedSmallInteger('priority')->default(100);
            $table->decimal('confidence', 5, 2)->default(100);
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });

        Schema::create('catalog_conflicts', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('entity_type', 64)->index();
            $table->unsignedBigInteger('entity_id')->nullable()->index();
            $table->string('field_or_relation', 128)->index();
            $table->string('severity', 24)->default('warning')->index();
            $table->string('status', 24)->default('open')->index();
            $table->jsonb('assertion_ids')->nullable();
            $table->jsonb('details')->nullable();
            $table->jsonb('resolution')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('vehicle_platforms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('make_id')->nullable()->constrained('vehicle_makes')->nullOnDelete();
            $table->string('code')->nullable()->index();
            $table->string('name')->nullable();
            $table->date('production_from')->nullable();
            $table->date('production_to')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
            $table->unique(['make_id', 'code']);
        });

        Schema::table('vehicle_generations', function (Blueprint $table): void {
            $table->foreignId('platform_id')->nullable()->after('model_id')->constrained('vehicle_platforms')->nullOnDelete();
            $table->date('production_from')->nullable();
            $table->date('production_to')->nullable();
        });

        Schema::table('vehicle_engines', function (Blueprint $table): void {
            $table->string('manufacturer_name')->nullable();
            $table->string('family')->nullable();
            $table->unsignedInteger('displacement_cc')->nullable()->index();
            $table->decimal('power_kw', 8, 2)->nullable()->index();
            $table->decimal('torque_nm', 8, 2)->nullable();
            $table->string('emissions_standard', 32)->nullable();
            $table->string('aspiration', 32)->nullable();
        });

        Schema::table('vehicle_configurations', function (Blueprint $table): void {
            $table->foreignId('platform_id')->nullable()->constrained('vehicle_platforms')->nullOnDelete();
            $table->string('commercial_name')->nullable();
            $table->unsignedSmallInteger('model_year_from')->nullable()->index();
            $table->unsignedSmallInteger('model_year_to')->nullable()->index();
            $table->date('production_from')->nullable();
            $table->date('production_to')->nullable();
            $table->string('market', 16)->nullable()->index();
            $table->string('fuel_type', 32)->nullable()->index();
            $table->unsignedInteger('displacement_cc')->nullable()->index();
            $table->decimal('power_kw', 8, 2)->nullable()->index();
            $table->string('eu_type_approval')->nullable()->index();
            $table->string('eu_type')->nullable()->index();
            $table->string('eu_variant')->nullable()->index();
            $table->string('eu_version')->nullable()->index();
            $table->string('canonical_fingerprint', 64)->nullable()->unique();
            $table->decimal('quality_score', 5, 2)->nullable();
        });

        Schema::create('vehicle_identifiers', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('configuration_id')->nullable()->constrained('vehicle_configurations')->cascadeOnDelete();
            $table->string('scheme', 48)->index();
            $table->string('namespace')->nullable()->index();
            $table->text('value_raw');
            $table->string('value_normalized', 255)->index();
            $table->foreignId('catalog_source_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('confidence', 5, 2)->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->timestampsTz();
            $table->index(['scheme', 'namespace', 'value_normalized'], 'vehicle_identifiers_lookup_idx');
        });

        Schema::create('vehicle_aliases', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('entity_type', 32)->index();
            $table->unsignedBigInteger('entity_id')->index();
            $table->text('alias');
            $table->string('normalized_alias', 255)->index();
            $table->string('language_code', 8)->nullable();
            $table->string('market', 16)->nullable();
            $table->foreignId('catalog_source_id')->nullable()->constrained()->nullOnDelete();
            $table->timestampsTz();
        });

        Schema::create('vehicle_technical_facts', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('configuration_id')->constrained('vehicle_configurations')->cascadeOnDelete();
            $table->string('fact_key', 100)->index();
            $table->text('value_text')->nullable();
            $table->decimal('value_numeric', 18, 6)->nullable();
            $table->string('unit', 32)->nullable();
            $table->jsonb('value_json')->nullable();
            $table->foreignId('catalog_source_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('confidence', 5, 2)->nullable();
            $table->timestampsTz();
            $table->index(['configuration_id', 'fact_key']);
        });

        Schema::create('catalog_parts', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->ulid('public_id')->unique();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->text('mpn_raw');
            $table->string('mpn_normalized', 255)->index();
            $table->string('name')->nullable();
            $table->longText('description')->nullable();
            $table->string('lifecycle_status', 32)->default('active')->index();
            $table->decimal('quality_score', 5, 2)->nullable()->index();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->unique(['brand_id', 'mpn_normalized']);
        });

        Schema::create('catalog_part_numbers', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('catalog_part_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->foreignId('oe_make_id')->nullable()->constrained('vehicle_makes')->nullOnDelete();
            $table->string('scheme', 32)->index();
            $table->string('namespace')->nullable()->index();
            $table->text('number_raw');
            $table->string('number_normalized', 255)->index();
            $table->string('number_compact', 255)->nullable()->index();
            $table->foreignId('catalog_source_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('confidence', 5, 2)->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->timestampsTz();
            $table->index(['brand_id', 'number_normalized']);
            $table->index(['oe_make_id', 'number_normalized']);
        });

        Schema::create('catalog_part_attributes', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('catalog_part_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained('attributes')->cascadeOnDelete();
            $table->text('value_text')->nullable();
            $table->decimal('value_number', 18, 6)->nullable();
            $table->string('unit', 32)->nullable();
            $table->decimal('normalized_number', 18, 6)->nullable();
            $table->string('normalized_unit', 32)->nullable();
            $table->jsonb('value_json')->nullable();
            $table->foreignId('catalog_source_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('confidence', 5, 2)->nullable();
            $table->timestampsTz();
            $table->index(['attribute_id', 'normalized_number']);
        });

        Schema::create('catalog_fitments', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('catalog_part_id')->constrained()->cascadeOnDelete();
            $table->foreignId('configuration_id')->constrained('vehicle_configurations')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('position', 48)->nullable()->index();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->string('status', 24)->default('candidate')->index();
            $table->decimal('confidence', 5, 2)->nullable()->index();
            $table->foreignId('catalog_source_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_fitment_key')->nullable();
            $table->timestampsTz();
            $table->index(['configuration_id', 'category_id', 'catalog_part_id'], 'catalog_fitments_vehicle_category_part_idx');
            $table->index(['catalog_part_id', 'configuration_id']);
        });

        Schema::create('catalog_fitment_constraints', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('catalog_fitment_id')->constrained()->cascadeOnDelete();
            $table->string('constraint_type', 64)->index();
            $table->string('operator', 24)->nullable();
            $table->text('value_text')->nullable();
            $table->decimal('value_number', 18, 6)->nullable();
            $table->string('unit', 32)->nullable();
            $table->jsonb('normalized')->nullable();
            $table->text('display_text')->nullable();
            $table->foreignId('catalog_source_id')->nullable()->constrained()->nullOnDelete();
            $table->timestampsTz();
        });

        Schema::create('catalog_part_relations', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('source_part_id')->constrained('catalog_parts')->cascadeOnDelete();
            $table->foreignId('target_part_id')->constrained('catalog_parts')->cascadeOnDelete();
            $table->string('relation_type', 48)->index();
            $table->boolean('is_directed')->default(true);
            $table->foreignId('catalog_source_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('confidence', 5, 2)->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();
            $table->unique(['source_part_id', 'target_part_id', 'relation_type', 'catalog_source_id'], 'catalog_part_relations_unique');
        });

        Schema::create('catalog_applicability_rules', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('catalog_part_id')->constrained()->cascadeOnDelete();
            $table->string('rule_type', 48)->index();
            $table->string('status', 24)->default('active')->index();
            $table->foreignId('catalog_source_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('confidence', 5, 2)->nullable();
            $table->text('display_text')->nullable();
            $table->timestampsTz();
        });

        Schema::create('catalog_applicability_rule_conditions', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('catalog_applicability_rule_id')->constrained()->cascadeOnDelete();
            $table->string('vehicle_fact_key', 100)->index();
            $table->string('operator', 24);
            $table->jsonb('value');
            $table->timestampsTz();
        });

        Schema::create('catalog_part_product_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('catalog_part_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->string('link_type', 24)->default('listing');
            $table->decimal('confidence', 5, 2)->default(100);
            $table->timestampsTz();
            $table->unique(['catalog_part_id', 'product_id', 'variant_id'], 'catalog_part_product_links_unique');
        });

        Schema::table('supplier_products', function (Blueprint $table): void {
            $table->foreignId('catalog_part_id')->nullable()->after('variant_id')->constrained('catalog_parts')->nullOnDelete();
            $table->decimal('mapping_confidence', 5, 2)->nullable()->after('mapping_status');
            $table->index(['catalog_part_id', 'mapping_status']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX vehicle_aliases_trgm_idx ON vehicle_aliases USING gin (alias gin_trgm_ops)');
            DB::statement('CREATE INDEX catalog_parts_name_trgm_idx ON catalog_parts USING gin (name gin_trgm_ops)');
            DB::statement('CREATE INDEX catalog_source_records_payload_idx ON catalog_source_records USING gin (raw_payload)');
        }
    }

    public function down(): void
    {
        Schema::table('supplier_products', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('catalog_part_id');
            $table->dropColumn('mapping_confidence');
        });

        Schema::dropIfExists('catalog_part_product_links');
        Schema::dropIfExists('catalog_applicability_rule_conditions');
        Schema::dropIfExists('catalog_applicability_rules');
        Schema::dropIfExists('catalog_part_relations');
        Schema::dropIfExists('catalog_fitment_constraints');
        Schema::dropIfExists('catalog_fitments');
        Schema::dropIfExists('catalog_part_attributes');
        Schema::dropIfExists('catalog_part_numbers');
        Schema::dropIfExists('catalog_parts');
        Schema::dropIfExists('vehicle_technical_facts');
        Schema::dropIfExists('vehicle_aliases');
        Schema::dropIfExists('vehicle_identifiers');

        Schema::table('vehicle_configurations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('platform_id');
            $table->dropColumn(['commercial_name', 'model_year_from', 'model_year_to', 'production_from', 'production_to', 'market', 'fuel_type', 'displacement_cc', 'power_kw', 'eu_type_approval', 'eu_type', 'eu_variant', 'eu_version', 'canonical_fingerprint', 'quality_score']);
        });

        Schema::table('vehicle_engines', function (Blueprint $table): void {
            $table->dropColumn(['manufacturer_name', 'family', 'displacement_cc', 'power_kw', 'torque_nm', 'emissions_standard', 'aspiration']);
        });

        Schema::table('vehicle_generations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('platform_id');
            $table->dropColumn(['production_from', 'production_to']);
        });

        Schema::dropIfExists('vehicle_platforms');
        Schema::dropIfExists('catalog_conflicts');
        Schema::dropIfExists('catalog_mapping_rules');
        Schema::dropIfExists('catalog_source_assertions');
        Schema::dropIfExists('catalog_source_records');
        Schema::dropIfExists('catalog_import_runs');
        Schema::dropIfExists('catalog_source_releases');
        Schema::dropIfExists('catalog_source_schedules');
        Schema::dropIfExists('catalog_sources');
    }
};
