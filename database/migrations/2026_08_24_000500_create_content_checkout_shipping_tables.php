<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->text('canonical_url')->nullable();
            $table->string('og_image_path')->nullable();
            $table->boolean('robots_index')->default(true);
            $table->boolean('robots_follow')->default(true);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->text('canonical_url')->nullable();
            $table->string('og_image_path')->nullable();
            $table->boolean('robots_index')->default(true);
            $table->boolean('robots_follow')->default(true);
        });

        Schema::create('media_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disk')->default('public');
            $table->text('path');
            $table->string('filename');
            $table->string('mime_type', 128);
            $table->unsignedBigInteger('size_bytes');
            $table->string('alt_text')->nullable();
            $table->string('title')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->unique(['disk', 'path']);
        });

        Schema::create('article_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->timestamps();
        });

        Schema::create('articles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('article_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('content')->nullable();
            $table->string('featured_image_path')->nullable();
            $table->string('featured_image_alt')->nullable();
            $table->string('status', 24)->default('draft')->index();
            $table->boolean('is_featured')->default(false)->index();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->text('canonical_url')->nullable();
            $table->string('og_image_path')->nullable();
            $table->boolean('robots_index')->default(true);
            $table->boolean('robots_follow')->default(true);
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('payment_providers', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('driver', 48);
            $table->string('mode', 16)->default('sandbox');
            $table->boolean('is_active')->default(false)->index();
            $table->boolean('is_default')->default(false)->index();
            $table->unsignedInteger('position')->default(0);
            $table->text('credentials')->nullable();
            $table->jsonb('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('shipping_providers', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('driver', 48);
            $table->string('mode', 16)->default('sandbox');
            $table->boolean('is_active')->default(false)->index();
            $table->text('credentials')->nullable();
            $table->jsonb('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('shipping_methods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shipping_provider_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('type', 24)->default('courier');
            $table->decimal('base_price', 14, 2)->default(0);
            $table->decimal('free_over', 14, 2)->nullable();
            $table->string('currency', 3)->default('RON');
            $table->unsignedSmallInteger('estimated_days_min')->nullable();
            $table->unsignedSmallInteger('estimated_days_max')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('position')->default(0);
            $table->jsonb('rules')->nullable();
            $table->timestamps();
        });

        Schema::create('carts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('token')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 24)->default('active')->index();
            $table->string('currency', 3)->default('RON');
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('cart_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 14, 2);
            $table->jsonb('snapshot')->nullable();
            $table->timestamps();
            $table->unique(['cart_id', 'product_id', 'variant_id']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('payment_provider_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shipping_method_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('checkout_token')->nullable()->unique();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
        });

        Schema::create('payment_transactions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_provider_id')->constrained()->restrictOnDelete();
            $table->string('external_id')->nullable()->index();
            $table->string('idempotency_key')->unique();
            $table->string('type', 24)->default('payment');
            $table->string('status', 24)->default('pending')->index();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3);
            $table->text('redirect_url')->nullable();
            $table->text('error_message')->nullable();
            $table->jsonb('payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_provider_id')->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->string('event_type');
            $table->string('status', 24)->default('received')->index();
            $table->jsonb('payload');
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['payment_provider_id', 'external_id']);
        });

        Schema::create('shipments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shipping_provider_id')->constrained()->restrictOnDelete();
            $table->string('awb_number')->nullable()->unique();
            $table->string('status', 32)->default('draft')->index();
            $table->text('tracking_url')->nullable();
            $table->string('label_disk')->nullable();
            $table->text('label_path')->nullable();
            $table->decimal('cost', 14, 2)->nullable();
            $table->string('currency', 3)->default('RON');
            $table->jsonb('payload')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });

        Schema::create('shipment_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->string('external_id')->nullable();
            $table->string('status', 32);
            $table->string('description')->nullable();
            $table->string('location')->nullable();
            $table->timestampTz('occurred_at');
            $table->jsonb('payload')->nullable();
            $table->timestamps();
            $table->index(['shipment_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_events');
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('payment_webhook_events');
        Schema::dropIfExists('payment_transactions');

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('payment_provider_id');
            $table->dropConstrainedForeignId('shipping_method_id');
            $table->dropColumn(['checkout_token', 'paid_at', 'cancelled_at']);
        });

        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
        Schema::dropIfExists('shipping_methods');
        Schema::dropIfExists('shipping_providers');
        Schema::dropIfExists('payment_providers');
        Schema::dropIfExists('articles');
        Schema::dropIfExists('article_categories');
        Schema::dropIfExists('media_assets');

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['seo_title', 'seo_description', 'canonical_url', 'og_image_path', 'robots_index', 'robots_follow']);
        });

        Schema::table('categories', function (Blueprint $table): void {
            $table->dropColumn(['seo_title', 'seo_description', 'canonical_url', 'og_image_path', 'robots_index', 'robots_follow']);
        });
    }
};
