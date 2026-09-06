<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_sync_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('mode', 24)->index();
            $table->string('cron_expression', 100);
            $table->string('timezone')->default('Europe/Bucharest');
            $table->boolean('is_enabled')->default(true)->index();
            $table->timestampTz('last_dispatched_at')->nullable();
            $table->timestampsTz();
            $table->unique(['supplier_id', 'mode']);
            $table->index(['is_enabled', 'mode'], 'supplier_sync_schedules_dispatch_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_sync_schedules');
    }
};
