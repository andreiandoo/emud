<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dated maintenance a customer tracks for one of their vehicles: ITP, insurance, oil, filters,
 * timing belt. Due dates and due mileage are kept separately because owners think in whichever
 * comes first, and an interval lets a completed item schedule its own successor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_service_reminders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_vehicle_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40)->index();
            $table->string('label')->nullable();
            $table->date('due_on')->nullable();
            $table->unsignedInteger('due_at_km')->nullable();
            $table->unsignedSmallInteger('interval_months')->nullable();
            $table->unsignedInteger('interval_km')->nullable();
            $table->date('last_done_on')->nullable();
            $table->unsignedInteger('last_done_km')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index(['customer_vehicle_id', 'due_on']);

            // One reminder per kind per vehicle: two "next ITP" rows would leave the customer
            // guessing which date is real.
            $table->unique(['customer_vehicle_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_service_reminders');
    }
};
