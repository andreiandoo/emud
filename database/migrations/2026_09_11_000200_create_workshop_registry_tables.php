<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The national workshop registry.
 *
 * Two layers. The raw layer (data sources, source records, import runs) keeps exactly what each
 * source returned, so any normalised value can be traced back to it and re-derived without
 * fetching again. The domain layer (companies, workshops and everything hanging off them) is
 * rebuilt from it and never edited in a way that loses where a value came from.
 *
 * A company and a workshop are different things: one legal entity can run twenty workshops, and
 * a registered office is not evidence that anyone repairs a car there.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workshop_data_sources', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('name');
            $table->string('type', 32)->index();
            $table->boolean('is_enabled')->default(true);
            // Until a source's terms are cleared for publication its data is used internally
            // (matching, review, leads) and left out of anything public.
            $table->boolean('is_public_output_allowed')->default(false);
            $table->jsonb('configuration')->nullable();
            $table->jsonb('state')->nullable();
            $table->timestampTz('last_started_at')->nullable();
            $table->timestampTz('last_completed_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('workshop_import_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('data_source_id')->constrained('workshop_data_sources')->cascadeOnDelete();
            $table->string('status', 32)->default('pending')->index();
            $table->jsonb('scope')->nullable();
            $table->unsignedInteger('discovered_count')->default(0);
            $table->unsignedInteger('fetched_count')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('unchanged_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('retired_count')->default(0);
            $table->jsonb('metadata')->nullable();
            $table->text('error_summary')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('workshop_source_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('data_source_id')->constrained('workshop_data_sources')->cascadeOnDelete();
            $table->foreignId('import_run_id')->nullable()->constrained('workshop_import_runs')->nullOnDelete();
            $table->string('record_type', 32);
            // The record itself (a RAR authorisation document, an OSM element, a web page).
            $table->string('external_id', 191);
            // What stays the same across its revisions: a RAR audit file or ITP station code. A
            // renewed authorisation is a new record of the same workshop, not a new workshop.
            $table->string('identity_key', 191)->nullable();
            $table->string('county_code', 4)->nullable()->index();
            $table->text('source_reference')->nullable();
            $table->longText('raw_content')->nullable();
            $table->jsonb('payload')->nullable();
            $table->char('content_hash', 64);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->timestampTz('first_seen_at');
            $table->timestampTz('last_seen_at');
            $table->timestampTz('fetched_at');
            $table->timestampTz('content_changed_at')->nullable();
            $table->timestampTz('parsed_at')->nullable();
            $table->string('parse_status', 16)->default('pending');
            $table->text('parse_error')->nullable();
            $table->boolean('is_current')->default(true);
            $table->timestampsTz();
            $table->unique(['data_source_id', 'record_type', 'external_id']);
            $table->index(['data_source_id', 'parse_status']);
            $table->index(['data_source_id', 'is_current']);
            $table->index(['data_source_id', 'identity_key']);
        });

        Schema::create('workshop_companies', function (Blueprint $table): void {
            $table->id();
            $table->string('cui', 16)->nullable()->unique();
            $table->string('legal_name');
            $table->string('normalized_name')->index();
            $table->string('legal_form', 16)->nullable();
            $table->string('registration_number', 64)->nullable()->index();
            $table->string('euid', 64)->nullable();
            $table->boolean('is_vat_payer')->nullable();
            $table->string('status', 120)->nullable();
            $table->string('status_code', 16)->nullable();
            $table->text('registered_address')->nullable();
            $table->string('registered_locality')->nullable();
            $table->string('registered_county')->nullable();
            $table->string('county_code', 4)->nullable()->index();
            $table->string('registered_postal_code', 16)->nullable();
            $table->decimal('registered_latitude', 10, 7)->nullable();
            $table->decimal('registered_longitude', 10, 7)->nullable();
            $table->jsonb('caen_codes')->nullable();
            $table->boolean('has_automotive_caen')->default(false)->index();
            $table->string('website')->nullable();
            $table->string('discovered_via', 16)->nullable()->index();
            $table->unsignedTinyInteger('source_confidence')->nullable();
            $table->timestampTz('onrc_verified_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('workshops', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('workshop_companies')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->nullable()->index();
            $table->string('normalized_name')->index();
            $table->text('address')->nullable();
            $table->text('normalized_address')->nullable();
            $table->string('street')->nullable();
            $table->string('street_number', 64)->nullable();
            $table->string('locality')->nullable();
            $table->string('normalized_locality')->nullable()->index();
            $table->string('county')->nullable();
            $table->string('county_code', 4)->nullable()->index();
            $table->string('postal_code', 16)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('coordinates_source', 24)->nullable();
            $table->unsignedTinyInteger('coordinates_confidence')->nullable();
            $table->string('geocode_status', 16)->default('pending')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_rar_authorized')->default(false)->index();
            $table->boolean('is_itp')->default(false)->index();
            $table->boolean('is_gpl_gnc')->default(false)->index();
            $table->boolean('is_tlv')->default(false);
            $table->boolean('is_modification_authorized')->default(false);
            $table->boolean('is_dismantling')->default(false);
            $table->boolean('is_mobile')->default(false);
            $table->boolean('supports_4x4')->nullable()->index();
            $table->boolean('supports_ev')->nullable()->index();
            $table->boolean('supports_hybrid')->nullable()->index();
            $table->boolean('supports_trucks')->nullable()->index();
            $table->unsignedTinyInteger('offroad_score')->nullable()->index();
            $table->unsignedSmallInteger('workstations')->nullable();
            $table->unsignedSmallInteger('employees')->nullable();
            $table->unsignedTinyInteger('confidence_score')->nullable();
            $table->string('website_status', 16)->default('pending')->index();
            $table->timestampTz('website_checked_at')->nullable();
            $table->foreignId('merged_into_id')->nullable()->constrained('workshops')->nullOnDelete();
            $table->timestampTz('first_seen_at')->nullable();
            $table->timestampTz('last_seen_at')->nullable();
            $table->timestampTz('last_verified_at')->nullable();
            $table->timestampsTz();
            $table->index(['latitude', 'longitude']);
        });

        Schema::create('workshop_source_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workshop_id')->constrained('workshops')->cascadeOnDelete();
            $table->foreignId('data_source_id')->constrained('workshop_data_sources')->cascadeOnDelete();
            // One record decides one workshop. A merge moves the link; it never duplicates it.
            $table->foreignId('source_record_id')->unique()->constrained('workshop_source_records')->cascadeOnDelete();
            $table->string('external_id', 191);
            $table->string('match_type', 32);
            $table->unsignedTinyInteger('match_confidence');
            $table->jsonb('evidence')->nullable();
            $table->timestampsTz();
            $table->index(['workshop_id', 'data_source_id']);
        });

        Schema::create('workshop_contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workshop_id')->constrained('workshops')->cascadeOnDelete();
            $table->string('type', 16);
            $table->string('value');
            $table->string('normalized_value');
            $table->string('label')->nullable();
            $table->foreignId('data_source_id')->nullable()->constrained('workshop_data_sources')->nullOnDelete();
            $table->foreignId('source_record_id')->nullable()->constrained('workshop_source_records')->nullOnDelete();
            $table->text('source_url')->nullable();
            $table->unsignedTinyInteger('confidence_score');
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->timestampTz('first_seen_at');
            $table->timestampTz('last_seen_at');
            $table->timestampsTz();
            // The same number reported by RAR and by the workshop's website is two pieces of
            // evidence, not one: each source keeps its own row.
            $table->unique(['workshop_id', 'type', 'normalized_value', 'data_source_id'], 'workshop_contacts_source_unique');
            $table->index(['type', 'normalized_value']);
        });

        Schema::create('workshop_authorizations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workshop_id')->constrained('workshops')->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('workshop_companies')->nullOnDelete();
            $table->foreignId('data_source_id')->constrained('workshop_data_sources')->cascadeOnDelete();
            $table->foreignId('source_record_id')->unique()->constrained('workshop_source_records')->cascadeOnDelete();
            $table->string('authority', 16)->default('RAR');
            $table->string('system', 16)->index();
            $table->string('status', 16)->nullable();
            $table->string('authorization_number', 64)->nullable()->index();
            $table->string('exit_number', 64)->nullable();
            $table->string('audit_file_number', 32)->nullable()->index();
            $table->string('station_code', 32)->nullable();
            $table->string('authorization_class', 32)->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable()->index();
            $table->date('initially_authorized_at')->nullable();
            $table->unsignedSmallInteger('revision_number')->nullable();
            $table->date('revision_date')->nullable();
            $table->boolean('is_current')->default(true)->index();
            $table->jsonb('raw_data');
            $table->timestampsTz();
        });

        Schema::create('workshop_authorization_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workshop_authorization_id')->constrained('workshop_authorizations')->cascadeOnDelete();
            // Exactly as RAR sends it (A1_2_1_1), the dotted form it prints (A1.2.1.1), and the
            // hierarchy both imply. Nothing is collapsed to a generic label here.
            $table->string('code', 48)->index();
            $table->string('display_code', 48);
            $table->string('parent_code', 48)->nullable();
            $table->string('entry_code', 48);
            $table->unsignedTinyInteger('depth');
            $table->text('description')->nullable();
            $table->text('raw_description')->nullable();
            $table->jsonb('vehicle_categories')->nullable();
            $table->jsonb('restrictions')->nullable();
            $table->jsonb('limitations')->nullable();
            $table->jsonb('observations')->nullable();
            $table->jsonb('raw_data')->nullable();
            $table->timestampsTz();
            $table->unique(['workshop_authorization_id', 'code']);
        });

        Schema::create('workshop_service_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('workshop_service_types')->nullOnDelete();
            $table->string('key', 64)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestampsTz();
        });

        Schema::create('workshop_services', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workshop_id')->constrained('workshops')->cascadeOnDelete();
            $table->foreignId('service_type_id')->constrained('workshop_service_types')->cascadeOnDelete();
            $table->foreignId('source_record_id')->nullable()->constrained('workshop_source_records')->nullOnDelete();
            // rar_authorization | osm | website | manual | inferred. A service RAR authorised and
            // the same service a website advertises are separate rows, never merged into one.
            $table->string('evidence_type', 24);
            $table->jsonb('evidence')->nullable();
            $table->boolean('is_authorized')->nullable();
            $table->unsignedTinyInteger('confidence_score');
            $table->timestampsTz();
            $table->unique(['workshop_id', 'service_type_id', 'evidence_type']);
            $table->index(['service_type_id', 'evidence_type']);
        });

        Schema::create('workshop_capabilities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workshop_id')->constrained('workshops')->cascadeOnDelete();
            $table->string('capability', 32);
            $table->boolean('value')->nullable();
            $table->unsignedTinyInteger('score');
            $table->string('basis', 24);
            $table->jsonb('evidence')->nullable();
            $table->timestampsTz();
            $table->unique(['workshop_id', 'capability']);
            $table->index(['capability', 'value']);
        });

        Schema::create('workshop_match_candidates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workshop_a_id')->constrained('workshops')->cascadeOnDelete();
            $table->foreignId('workshop_b_id')->constrained('workshops')->cascadeOnDelete();
            $table->unsignedTinyInteger('score');
            $table->jsonb('evidence')->nullable();
            $table->string('status', 16)->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampsTz();
            $table->unique(['workshop_a_id', 'workshop_b_id']);
        });

        // How an ONRC company or an OSM point was matched, or why it was not. Kept even when the
        // answer is "ambiguous", so a reviewer sees the candidates the matcher weighed.
        Schema::create('workshop_record_matches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_record_id')->constrained('workshop_source_records')->cascadeOnDelete();
            $table->string('target_type', 16);
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('status', 16)->index();
            $table->string('method', 32)->nullable();
            $table->unsignedTinyInteger('score')->nullable();
            $table->jsonb('candidates')->nullable();
            $table->jsonb('evidence')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('decided_at')->nullable();
            $table->timestampsTz();
            $table->unique(['source_record_id', 'target_type']);
            $table->index(['target_type', 'target_id']);
        });

        Schema::create('workshop_website_candidates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workshop_id')->constrained('workshops')->cascadeOnDelete();
            $table->text('url');
            $table->string('domain')->index();
            $table->string('discovered_via', 16);
            $table->unsignedTinyInteger('confidence');
            $table->jsonb('evidence')->nullable();
            $table->string('validation_status', 16)->default('pending')->index();
            $table->timestampTz('validated_at')->nullable();
            $table->timestampTz('crawled_at')->nullable();
            $table->timestampsTz();
            $table->unique(['workshop_id', 'domain']);
        });

        // Fuzzy name and address matching and the admin search lean on trigram similarity. Only
        // PostgreSQL has it; elsewhere search falls back to plain LIKE.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
            DB::statement('CREATE INDEX workshops_normalized_name_trgm_idx ON workshops USING gin (normalized_name gin_trgm_ops)');
            DB::statement('CREATE INDEX workshops_normalized_address_trgm_idx ON workshops USING gin (normalized_address gin_trgm_ops)');
            DB::statement('CREATE INDEX workshop_companies_normalized_name_trgm_idx ON workshop_companies USING gin (normalized_name gin_trgm_ops)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('workshop_website_candidates');
        Schema::dropIfExists('workshop_record_matches');
        Schema::dropIfExists('workshop_match_candidates');
        Schema::dropIfExists('workshop_capabilities');
        Schema::dropIfExists('workshop_services');
        Schema::dropIfExists('workshop_service_types');
        Schema::dropIfExists('workshop_authorization_activities');
        Schema::dropIfExists('workshop_authorizations');
        Schema::dropIfExists('workshop_contacts');
        Schema::dropIfExists('workshop_source_links');
        Schema::dropIfExists('workshops');
        Schema::dropIfExists('workshop_companies');
        Schema::dropIfExists('workshop_source_records');
        Schema::dropIfExists('workshop_import_runs');
        Schema::dropIfExists('workshop_data_sources');
    }
};
