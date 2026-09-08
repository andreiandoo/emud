<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            // Qualification / pipeline. A supplier exists as a commercial prospect long
            // before it has credentials, so this lifecycle is independent of is_active.
            $table->string('supplier_type', 24)->default('distributor')->after('code');
            $table->string('onboarding_status', 32)->default('not_started')->index()->after('supplier_type');
            $table->string('strategic_role', 24)->nullable()->after('onboarding_status');
            $table->char('country_code', 2)->nullable()->index()->after('strategic_role');
            $table->text('website')->nullable()->after('country_code');
            $table->jsonb('contact')->nullable()->after('website');
            $table->unsignedSmallInteger('qualification_score')->nullable()->after('contact');
            $table->unsignedSmallInteger('readiness_score')->nullable()->after('qualification_score');
            $table->unsignedSmallInteger('offroad_fit_score')->nullable()->after('readiness_score');

            // Capabilities. Null means "not established yet" and is deliberately distinct
            // from false ("supplier documented that it does not offer this").
            foreach ([
                'supports_catalog',
                'supports_prices',
                'supports_stock',
                'supports_realtime_stock',
                'supports_order_api',
                'supports_tracking_api',
                'supports_returns_api',
                'supports_tecdoc',
                'supports_aces_pies',
            ] as $capability) {
                $table->boolean($capability)->nullable();
            }

            // Commercial terms consumed by landed cost and routing.
            $table->decimal('dropship_fee', 10, 2)->nullable();
            $table->decimal('packaging_fee', 10, 2)->nullable();
            $table->decimal('minimum_order_value', 12, 2)->nullable();
            $table->unsignedInteger('minimum_order_quantity')->nullable();
            $table->decimal('free_shipping_threshold', 12, 2)->nullable();
            $table->unsignedSmallInteger('payment_terms_days')->nullable();
            $table->string('terms_currency', 3)->nullable();

            // Fulfilment branding. Same null-means-unknown rule as the capabilities.
            $table->boolean('blind_shipping')->nullable();
            $table->boolean('neutral_packaging')->nullable();
            $table->boolean('merchant_as_sender')->nullable();
            $table->boolean('supplier_invoice_in_parcel')->nullable();

            $table->unsignedSmallInteger('return_window_days')->nullable();
            $table->decimal('restocking_fee_percent', 5, 2)->nullable();
            $table->string('return_freight_payer', 24)->nullable();
            $table->text('map_policy')->nullable();
            $table->text('marketplace_restrictions')->nullable();

            $table->jsonb('allowed_countries')->nullable();
            $table->jsonb('excluded_countries')->nullable();
            $table->time('cutoff_time')->nullable();
            $table->unsignedSmallInteger('default_dispatch_days_min')->nullable();
            $table->unsignedSmallInteger('default_dispatch_days_max')->nullable();

            // Free-form questionnaire answers that no engine reads.
            $table->jsonb('commercial_profile')->nullable();

            // Account-opening workflow.
            $table->text('onboarding_notes')->nullable();
            $table->timestampTz('contacted_at')->nullable();
            $table->text('next_action')->nullable();
            $table->timestampTz('next_action_due_at')->nullable();

            $table->index(['onboarding_status', 'qualification_score'], 'suppliers_pipeline_idx');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->dropIndex('suppliers_pipeline_idx');
            $table->dropColumn([
                'supplier_type',
                'onboarding_status',
                'strategic_role',
                'country_code',
                'website',
                'contact',
                'qualification_score',
                'readiness_score',
                'offroad_fit_score',
                'supports_catalog',
                'supports_prices',
                'supports_stock',
                'supports_realtime_stock',
                'supports_order_api',
                'supports_tracking_api',
                'supports_returns_api',
                'supports_tecdoc',
                'supports_aces_pies',
                'dropship_fee',
                'packaging_fee',
                'minimum_order_value',
                'minimum_order_quantity',
                'free_shipping_threshold',
                'payment_terms_days',
                'terms_currency',
                'blind_shipping',
                'neutral_packaging',
                'merchant_as_sender',
                'supplier_invoice_in_parcel',
                'return_window_days',
                'restocking_fee_percent',
                'return_freight_payer',
                'map_policy',
                'marketplace_restrictions',
                'allowed_countries',
                'excluded_countries',
                'cutoff_time',
                'default_dispatch_days_min',
                'default_dispatch_days_max',
                'commercial_profile',
                'onboarding_notes',
                'contacted_at',
                'next_action',
                'next_action_due_at',
            ]);
        });
    }
};
