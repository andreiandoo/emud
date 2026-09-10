<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A supplier article carries far more identity than the one EAN and one MPN
        // supplier_products has columns for: OE numbers, aftermarket cross references,
        // superseded numbers, sometimes a TecDoc article. Until now those sat inside a
        // JSON payload the matcher never read, so an article identifiable only by its
        // OE number was simply unmatched.
        Schema::create('supplier_product_identifiers', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('supplier_product_id')->constrained()->cascadeOnDelete();
            $table->string('type', 24);
            $table->text('value');
            $table->string('compact_value', 255);
            // Cross references name their own manufacturer ("MANN W 712/75"), which is
            // what makes them usable as brand+number rather than a bare number.
            $table->string('brand')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['supplier_product_id', 'type', 'compact_value'], 'supplier_product_identifiers_unique');
            $table->index(['type', 'compact_value'], 'supplier_product_identifiers_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_product_identifiers');
    }
};
