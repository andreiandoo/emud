<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A photograph of the customer's own car. Until now a saved car could only borrow the picture of
 * the collection it belongs to, which is a Jimny, not their Jimny.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_vehicles', function (Blueprint $table): void {
            $table->string('photo_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('customer_vehicles', function (Blueprint $table): void {
            $table->dropColumn('photo_path');
        });
    }
};
