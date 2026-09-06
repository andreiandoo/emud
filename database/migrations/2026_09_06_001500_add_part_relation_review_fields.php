<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_unresolved_part_relations', function (Blueprint $table): void {
            $table->unsignedInteger('resolution_attempts')->default(0)->after('status');
            $table->timestampTz('last_resolution_attempt_at')->nullable()->after('resolution_attempts');
            $table->foreignId('reviewed_by')->nullable()->after('resolved_at')->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('review_note')->nullable()->after('reviewed_at');
            $table->index(['status', 'last_resolution_attempt_at'], 'catalog_unresolved_relations_attempt_idx');
        });
    }

    public function down(): void
    {
        Schema::table('catalog_unresolved_part_relations', function (Blueprint $table): void {
            $table->dropIndex('catalog_unresolved_relations_attempt_idx');
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn([
                'resolution_attempts',
                'last_resolution_attempt_at',
                'reviewed_at',
                'review_note',
            ]);
        });
    }
};
