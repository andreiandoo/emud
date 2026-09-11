<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A public listing can now stand on a workshop from the national registry.
 *
 * The registry keeps the evidence and is rebuilt by every import; the listing keeps what the
 * shop curates on top of it. workshop_id says which workshop a listing speaks for (one listing
 * per workshop), registry_locked lists the fields an admin has taken over so the next sync leaves
 * them alone, and registry_synced_at says when the listing last followed its workshop.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_shops', function (Blueprint $table): void {
            $table->foreignId('workshop_id')->nullable()->unique()->constrained('workshops')->nullOnDelete();
            $table->jsonb('registry_locked')->nullable();
            $table->timestampTz('registry_synced_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('service_shops', function (Blueprint $table): void {
            $table->dropForeign(['workshop_id']);
            $table->dropUnique(['workshop_id']);
            $table->dropColumn(['workshop_id', 'registry_locked', 'registry_synced_at']);
        });
    }
};
