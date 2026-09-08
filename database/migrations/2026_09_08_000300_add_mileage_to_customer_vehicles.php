<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_vehicles', function (Blueprint $table): void {
            // Mileage-based reminders cannot be judged without knowing where the car is now,
            // and the reading is only meaningful alongside the date it was taken.
            $table->unsignedInteger('mileage_km')->nullable()->after('registration_number');
            $table->date('mileage_recorded_on')->nullable()->after('mileage_km');
        });
    }

    public function down(): void
    {
        Schema::table('customer_vehicles', function (Blueprint $table): void {
            $table->dropColumn(['mileage_km', 'mileage_recorded_on']);
        });
    }
};
