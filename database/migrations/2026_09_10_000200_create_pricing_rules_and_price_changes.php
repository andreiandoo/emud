<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The shop's pricing policy. Until now the only policy was the supplier's: a variant
        // got the recommended price once, at import, and never moved again while the cost
        // underneath it did.
        Schema::create('pricing_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('scope_type', 16);
            $table->unsignedBigInteger('scope_id')->nullable();
            // Gross margin on the landed cost, before VAT: what the rule is for.
            $table->decimal('target_gross_margin_percent', 5, 2);
            // Optional overrides of the shop-wide guardrails for this scope.
            $table->decimal('minimum_contribution_percent', 5, 2)->nullable();
            $table->decimal('max_auto_change_percent', 5, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['scope_type', 'scope_id']);
        });

        // Every automatic price decision, applied or waiting for a human. A price that moves
        // on its own has to be explainable afterwards: which offer, which rule, which floor.
        Schema::create('price_changes', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->decimal('old_price', 14, 2)->nullable();
            $table->decimal('new_price', 14, 2);
            $table->string('currency', 3);
            $table->string('status', 16)->index();
            $table->string('trigger', 24);
            $table->jsonb('decision')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('decided_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['variant_id', 'status']);
        });

        Schema::table('products', function (Blueprint $table): void {
            // Manual means an operator owns the price; automatic repricing leaves it alone.
            $table->string('pricing_mode', 16)->default('auto')->index();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('pricing_mode');
        });

        Schema::dropIfExists('price_changes');
        Schema::dropIfExists('pricing_rules');
    }
};
