<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Saved products, either for the account as a whole or for one car in the garage. A customer
 * with two vehicles should not get one undifferentiated list, so the vehicle is part of the
 * item rather than a separate list per car.
 */
return new class extends Migration
{
    private const INDEX = 'wishlist_items_unique_per_scope';

    public function up(): void
    {
        Schema::create('wishlist_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();

            // nullOnDelete: removing a car should demote its saved parts to the account list
            // rather than throwing away things the customer chose to keep.
            $table->foreignId('customer_vehicle_id')->nullable()->constrained()->nullOnDelete();

            // The verdict as it stood when the product was saved. A later catalogue change that
            // makes it incompatible then shows as a change rather than silently rewriting
            // history.
            $table->string('verdict_when_saved', 32)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'customer_vehicle_id']);
        });

        // NULLs compare as distinct inside a plain unique index, so the same product could be
        // saved repeatedly whenever variant or vehicle was null. COALESCE gives every scope a
        // comparable value; the expression form works on both PostgreSQL and SQLite.
        DB::statement(
            'CREATE UNIQUE INDEX '.self::INDEX.' ON wishlist_items '
            .'(user_id, product_id, COALESCE(variant_id, 0), COALESCE(customer_vehicle_id, 0))'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS '.self::INDEX);
        Schema::dropIfExists('wishlist_items');
    }
};
