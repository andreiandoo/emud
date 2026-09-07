<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_api_external_identities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('catalog_api_consumer_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32)->index();
            $table->string('external_user_id', 255);
            $table->string('subscription', 32)->nullable()->index();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('last_seen_at')->nullable()->index();
            $table->timestampsTz();
            $table->unique(['provider', 'external_user_id']);
            $table->index(['catalog_api_consumer_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_api_external_identities');
    }
};
