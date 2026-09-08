<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Until now a bad feed row was swallowed by report() and survived only as a
        // number in failed_count, so a supplier changing its export schema was
        // invisible until someone noticed missing products.
        Schema::create('supplier_sync_errors', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('supplier_sync_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_identifier')->nullable();
            $table->string('error_type', 24)->index();
            $table->text('message');
            $table->jsonb('raw_payload')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['supplier_sync_run_id', 'error_type'], 'supplier_sync_errors_run_type_idx');
            $table->index(['supplier_id', 'created_at'], 'supplier_sync_errors_supplier_idx');
        });

        Schema::table('supplier_sync_runs', function (Blueprint $table): void {
            // Rows the feed actually contained, including those rejected before they
            // ever reached the importer. processed only counts rows that got that far.
            $table->unsignedBigInteger('received_count')->default(0)->after('processed');
            $table->unsignedBigInteger('rejected_count')->default(0)->after('skipped_count');
            $table->unsignedBigInteger('retired_count')->default(0)->after('failed_count');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_sync_runs', function (Blueprint $table): void {
            $table->dropColumn(['received_count', 'rejected_count', 'retired_count']);
        });

        Schema::dropIfExists('supplier_sync_errors');
    }
};
