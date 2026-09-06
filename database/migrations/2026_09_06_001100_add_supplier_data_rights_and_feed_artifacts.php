<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->string('data_rights_class', 48)->default('unknown_pending_review')->index()->after('settings');
            $table->boolean('allow_internal_data')->default(true)->after('data_rights_class');
            $table->boolean('allow_ecommerce_data')->default(false)->after('allow_internal_data');
            $table->boolean('allow_derived_data')->default(false)->after('allow_ecommerce_data');
            $table->boolean('allow_api_redistribution')->default(false)->after('allow_derived_data');
            $table->boolean('attribution_required')->default(false)->after('allow_api_redistribution');
            $table->text('license_name')->nullable()->after('attribution_required');
            $table->text('license_url')->nullable()->after('license_name');
            $table->text('legal_notes')->nullable()->after('license_url');
        });

        Schema::create('supplier_feed_artifacts', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_sync_run_id')->nullable()->constrained('supplier_sync_runs')->nullOnDelete();
            $table->string('mode', 24)->index();
            $table->text('source_path');
            $table->string('filename')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestampTz('source_modified_at')->nullable();
            $table->string('checksum_sha256', 64)->index();
            $table->timestampTz('retrieved_at')->useCurrent()->index();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
            $table->index(['supplier_id', 'mode', 'retrieved_at'], 'supplier_feed_artifacts_source_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_feed_artifacts');

        Schema::table('suppliers', function (Blueprint $table): void {
            $table->dropColumn([
                'data_rights_class',
                'allow_internal_data',
                'allow_ecommerce_data',
                'allow_derived_data',
                'allow_api_redistribution',
                'attribution_required',
                'license_name',
                'license_url',
                'legal_notes',
            ]);
        });
    }
};
