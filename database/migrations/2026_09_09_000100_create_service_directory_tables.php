<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_shops', function (Blueprint $table): void {
            // The city is part of the public URL, so it needs a stable slug of its own rather
            // than being slugged at render time — a rename would otherwise silently move every
            // workshop in that city to a new address.
            $table->string('city_slug', 96)->nullable()->after('city')->index();
            $table->string('logo_path')->nullable()->after('description');

            // Fixed option lists rather than tables: they are only ever displayed and filtered
            // against a short set the application already knows, and three join tables for
            // nine values each would cost more to read than they save.
            $table->jsonb('amenities')->nullable()->after('specialities');
            $table->jsonb('payment_methods')->nullable()->after('amenities');
            $table->jsonb('certifications')->nullable()->after('payment_methods');

            $table->boolean('accepts_appointments')->default(true)->after('fits_parts_bought_here');
        });

        Schema::create('service_shop_hours', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_shop_id')->constrained()->cascadeOnDelete();
            // ISO weekday: 1 = Monday … 7 = Sunday, matching Carbon, so "open now" never has to
            // translate between two numbering schemes.
            $table->unsignedTinyInteger('weekday');
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->boolean('is_closed')->default(false);
            $table->timestamps();

            $table->unique(['service_shop_id', 'weekday']);
        });

        Schema::create('service_shop_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_shop_id')->constrained()->cascadeOnDelete();
            $table->string('disk')->default('public');
            $table->text('path');
            $table->string('alt_text')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['service_shop_id', 'position']);
        });

        Schema::create('service_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('icon', 32)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('services', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_category_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            // What a workshop does, tied to what the shop sells. Without this link the service
            // taxonomy is decoration; with it, "brake pad replacement" can offer brake pads.
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->unsignedSmallInteger('typical_duration_minutes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('service_shop_service', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            // A range, not a price: workshops quote "from" and the honest ones quote a ceiling
            // too. Both nullable, because plenty will only say "call us".
            $table->decimal('price_from', 10, 2)->nullable();
            $table->decimal('price_to', 10, 2)->nullable();
            $table->string('currency', 3)->default('RON');
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['service_shop_id', 'service_id']);
        });

        Schema::create('service_shop_vehicle_make', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_make_id')->constrained()->cascadeOnDelete();

            $table->unique(['service_shop_id', 'vehicle_make_id']);
        });

        Schema::create('service_appointments', function (Blueprint $table): void {
            $table->id();
            // Looked up by token on the public confirmation page, so the id is never enough to
            // read someone else's request.
            $table->uuid('token')->unique();
            $table->foreignId('service_shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            // The order this fitting is for. This is the whole reason the directory sits inside
            // a parts shop rather than beside one.
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();

            $table->string('customer_name');
            $table->string('customer_phone', 32);
            $table->string('customer_email')->nullable();
            // Free text for a visitor with no garage: refusing the request until they build a
            // vehicle record would lose the lead.
            $table->string('vehicle_label')->nullable();

            $table->date('preferred_date')->nullable();
            $table->string('preferred_slot', 16)->default('anytime');
            $table->text('message')->nullable();

            $table->string('status', 24)->default('requested')->index();
            $table->text('internal_note')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(['service_shop_id', 'status']);
        });

        Schema::create('service_shop_lead_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 32)->index();
            $table->timestamp('created_at')->useCurrent();

            // No visitor identifier of any kind. These rows exist to bill a workshop for what it
            // received, which needs counts and dates; keeping who did it would turn a billing
            // table into a tracking log with nothing gained.
            $table->index(['service_shop_id', 'type', 'created_at']);
        });

        $this->backfillCitySlugs();
    }

    public function down(): void
    {
        Schema::dropIfExists('service_shop_lead_events');
        Schema::dropIfExists('service_appointments');
        Schema::dropIfExists('service_shop_vehicle_make');
        Schema::dropIfExists('service_shop_service');
        Schema::dropIfExists('services');
        Schema::dropIfExists('service_categories');
        Schema::dropIfExists('service_shop_media');
        Schema::dropIfExists('service_shop_hours');

        Schema::table('service_shops', function (Blueprint $table): void {
            $table->dropColumn([
                'city_slug', 'logo_path', 'amenities', 'payment_methods',
                'certifications', 'accepts_appointments',
            ]);
        });
    }

    /**
     * Existing listings have a city but no slug, and their public URL is about to be built from
     * one. Filled here rather than by a command so no deploy order can leave them unreachable.
     */
    private function backfillCitySlugs(): void
    {
        DB::table('service_shops')->select('id', 'city')->orderBy('id')->chunk(200, function ($rows): void {
            foreach ($rows as $row) {
                DB::table('service_shops')->where('id', $row->id)->update([
                    'city_slug' => Str::slug((string) $row->city) ?: 'necunoscut',
                ]);
            }
        });
    }
};
