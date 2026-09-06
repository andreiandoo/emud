<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_sources', function (Blueprint $table): void {
            $table->string('canonicalizer_class')->nullable()->after('connector_class');
        });

        Schema::table('catalog_source_records', function (Blueprint $table): void {
            $table->string('canonical_entity_type', 64)->nullable()->index();
            $table->unsignedBigInteger('canonical_entity_id')->nullable()->index();
            $table->decimal('mapping_confidence', 5, 2)->nullable();
            $table->index(['canonical_entity_type', 'canonical_entity_id'], 'catalog_source_records_canonical_idx');
        });

        Schema::create('catalog_api_consumers', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('email')->nullable();
            $table->string('plan', 32)->default('basic')->index();
            $table->unsignedBigInteger('monthly_quota')->default(100);
            $table->unsignedBigInteger('requests_used')->default(0);
            $table->timestampTz('period_started_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
        });

        Schema::create('catalog_api_keys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('catalog_api_consumer_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('key_prefix', 16)->index();
            $table->string('key_hash', 64)->unique();
            $table->jsonb('scopes')->nullable();
            $table->timestampTz('last_used_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('catalog_change_events', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->ulid('public_id')->unique();
            $table->string('event_type', 64)->index();
            $table->string('entity_type', 64)->index();
            $table->unsignedBigInteger('entity_id')->index();
            $table->jsonb('payload')->nullable();
            $table->boolean('api_redistributable')->default(false)->index();
            $table->timestampTz('occurred_at')->useCurrent()->index();
            $table->timestampsTz();
            $table->index(['entity_type', 'entity_id', 'occurred_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE vehicle_configurations ALTER COLUMN drive_type SET DEFAULT 'unknown'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE vehicle_configurations ALTER COLUMN drive_type SET DEFAULT '4x4'");
        }

        Schema::dropIfExists('catalog_change_events');
        Schema::dropIfExists('catalog_api_keys');
        Schema::dropIfExists('catalog_api_consumers');

        Schema::table('catalog_source_records', function (Blueprint $table): void {
            $table->dropIndex('catalog_source_records_canonical_idx');
            $table->dropColumn(['canonical_entity_type', 'canonical_entity_id', 'mapping_confidence']);
        });

        Schema::table('catalog_sources', function (Blueprint $table): void {
            $table->dropColumn('canonicalizer_class');
        });
    }
};
