<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 16)->default('shipping');
            $table->string('company')->nullable();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone', 32)->nullable();
            $table->string('country_code', 2)->default('RO');
            $table->string('county')->nullable();
            $table->string('city');
            $table->string('postal_code', 16)->nullable();
            $table->string('line_1');
            $table->string('line_2')->nullable();
            $table->string('vat_number')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('billing_address_id')->nullable()->constrained('addresses')->nullOnDelete();
            $table->foreignId('shipping_address_id')->nullable()->constrained('addresses')->nullOnDelete();
            $table->string('status', 24)->default('pending')->index();
            $table->string('payment_status', 24)->default('pending')->index();
            $table->string('fulfillment_status', 24)->default('unfulfilled')->index();
            $table->string('currency', 3)->default('RON');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('shipping_total', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2)->default(0);
            $table->string('customer_email');
            $table->string('customer_phone', 32)->nullable();
            $table->text('customer_note')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('placed_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 14, 2);
            $table->decimal('unit_cost', 14, 4)->nullable();
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('line_total', 14, 2);
            $table->string('dropship_status', 24)->default('pending')->index();
            $table->string('supplier_order_reference')->nullable();
            $table->jsonb('snapshot')->nullable();
            $table->timestamps();
        });

        Schema::create('order_status_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24);
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('saved_searches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->jsonb('criteria');
            $table->boolean('notifications_enabled')->default(true);
            $table->timestamp('last_notified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('product_alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->string('type', 24);
            $table->decimal('target_price', 14, 2)->nullable();
            $table->string('currency', 3)->default('RON');
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('condition_met')->default(false);
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();
            $table->index(['product_id', 'variant_id', 'type']);
        });

        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('email_stock_alerts')->default(true);
            $table->boolean('email_price_alerts')->default(true);
            $table->boolean('email_vehicle_news')->default(true);
            $table->boolean('email_events')->default(true);
            $table->boolean('sms_critical_updates')->default(false);
            $table->jsonb('channels')->nullable();
            $table->timestamps();
        });

        Schema::create('community_events', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('summary')->nullable();
            $table->longText('description')->nullable();
            $table->string('format', 16)->default('offline');
            $table->string('status', 24)->default('draft')->index();
            $table->string('venue_name')->nullable();
            $table->text('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('online_url')->nullable();
            $table->timestampTz('starts_at')->index();
            $table->timestampTz('ends_at')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->boolean('is_public')->default(true);
            $table->jsonb('vehicle_requirements')->nullable();
            $table->timestamps();
        });

        Schema::create('community_event_registrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('community_events')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 24)->default('registered');
            $table->unsignedSmallInteger('guest_count')->default(0);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_event_registrations');
        Schema::dropIfExists('community_events');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('product_alerts');
        Schema::dropIfExists('saved_searches');
        Schema::dropIfExists('order_status_history');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('addresses');
    }
};
