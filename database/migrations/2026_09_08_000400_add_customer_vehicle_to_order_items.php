<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records which of the customer's vehicles was active when a line was bought, so the garage can
 * answer "what did I put on this car last time". Stamped at checkout rather than inferred
 * later: guessing which car an old order was for would present a guess as a record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            // nullOnDelete, not cascade: deleting a car from the garage must not erase the
            // order history that the accounts depend on.
            $table->foreignId('customer_vehicle_id')->nullable()->after('variant_id')
                ->constrained()->nullOnDelete();
            $table->index(['customer_vehicle_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('customer_vehicle_id');
        });
    }
};
