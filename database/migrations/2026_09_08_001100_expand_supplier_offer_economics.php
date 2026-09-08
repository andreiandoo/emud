<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_warehouses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name')->nullable();
            $table->char('country_code', 2)->nullable();
            $table->string('city')->nullable();
            $table->time('cutoff_time')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['supplier_id', 'code']);
        });

        Schema::table('supplier_offers', function (Blueprint $table): void {
            $table->foreignId('supplier_warehouse_id')->nullable()->after('supplier_product_id')->constrained()->nullOnDelete();
            $table->string('warehouse_code')->nullable()->after('supplier_warehouse_id');

            // cost_price stays the supplier's own net cost in its own currency and is
            // never overwritten. The converted value lives alongside it, with the rate
            // and the day it came from, so a historical margin can be re-derived.
            $table->decimal('cost_gross', 14, 4)->nullable()->after('cost_price');
            $table->string('base_currency', 3)->nullable()->after('currency');
            $table->decimal('base_cost_net', 14, 4)->nullable()->after('base_currency');
            $table->decimal('fx_rate', 18, 8)->nullable()->after('base_cost_net');
            $table->timestampTz('fx_rate_at')->nullable()->after('fx_rate');

            $table->decimal('map_price', 14, 2)->nullable();
            $table->decimal('msrp', 14, 2)->nullable();

            // Fulfilment cost carried per offer, not just per supplier: DSI Automotive
            // publishes dropship fees from a few dollars to seventy-plus on the same
            // account, depending on the item.
            $table->decimal('dropship_fee', 12, 2)->nullable();
            $table->decimal('handling_fee', 12, 2)->nullable();
            $table->decimal('shipping_cost_estimate', 12, 2)->nullable();

            $table->unsignedInteger('pack_quantity')->default(1);
            $table->unsignedSmallInteger('dispatch_days_min')->nullable();
            $table->unsignedSmallInteger('dispatch_days_max')->nullable();

            $table->string('shipping_class', 24)->nullable()->index();
            $table->decimal('weight_kg', 10, 3)->nullable();
            $table->decimal('packed_weight_kg', 10, 3)->nullable();
            $table->decimal('length_cm', 10, 2)->nullable();
            $table->decimal('width_cm', 10, 2)->nullable();
            $table->decimal('height_cm', 10, 2)->nullable();
            $table->boolean('oversize_flag')->default(false);
            $table->boolean('hazmat_flag')->default(false);

            // Tri-state like the supplier capabilities: null means the supplier has not
            // told us whether this particular article may be dropshipped.
            $table->boolean('is_dropship_eligible')->nullable();
            $table->boolean('is_active')->default(true)->index();

            $table->string('source_type', 16)->nullable();
            $table->timestampTz('source_updated_at')->nullable();
            $table->timestampTz('last_verified_at')->nullable();

            // A marketplace such as ALZURA or Tyre100 exposes one article held by many
            // underlying sellers. They stay one supplier here, with the seller kept on
            // the offer, rather than becoming thousands of supplier rows.
            $table->string('source_seller_ref')->nullable();
            $table->string('source_seller_name')->nullable();
        });

        Schema::create('supplier_stock_history', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('supplier_offer_id')->constrained()->cascadeOnDelete();
            $table->integer('stock_quantity')->nullable();
            $table->string('stock_status', 24);
            $table->timestampTz('recorded_at')->useCurrent();
            $table->index(['supplier_offer_id', 'recorded_at']);
        });

        Schema::create('exchange_rates', function (Blueprint $table): void {
            $table->id();
            $table->string('base_currency', 3);
            $table->string('quote_currency', 3);
            $table->decimal('rate', 18, 8);
            $table->date('rate_date');
            $table->string('source', 32)->default('manual');
            $table->timestamps();
            $table->unique(['base_currency', 'quote_currency', 'rate_date']);
            $table->index(['quote_currency', 'rate_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('supplier_stock_history');

        Schema::table('supplier_offers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('supplier_warehouse_id');
            $table->dropColumn([
                'warehouse_code', 'cost_gross', 'base_currency', 'base_cost_net', 'fx_rate', 'fx_rate_at',
                'map_price', 'msrp', 'dropship_fee', 'handling_fee', 'shipping_cost_estimate',
                'pack_quantity', 'dispatch_days_min', 'dispatch_days_max',
                'shipping_class', 'weight_kg', 'packed_weight_kg', 'length_cm', 'width_cm', 'height_cm',
                'oversize_flag', 'hazmat_flag', 'is_dropship_eligible', 'is_active',
                'source_type', 'source_updated_at', 'last_verified_at', 'source_seller_ref', 'source_seller_name',
            ]);
        });

        Schema::dropIfExists('supplier_warehouses');
    }
};
