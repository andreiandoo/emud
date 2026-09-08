<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Articles move from one HTML blob to ordered, typed blocks, and gain a link to the vehicles
 * they are about.
 *
 * Blocks rather than embedded markup because a parts carousel has to be resolved at render
 * time: a carousel frozen into HTML would still be advertising parts that went out of stock or
 * out of the catalogue months ago.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_blocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32)->index();
            $table->unsignedInteger('position')->default(0);

            // One jsonb payload per block rather than a column per block kind: the shapes differ
            // and each type validates its own on the way in.
            $table->jsonb('data')->nullable();
            $table->timestamps();

            $table->index(['article_id', 'position']);
        });

        Schema::create('article_vehicles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('make_id')->nullable()->constrained('vehicle_makes')->cascadeOnDelete();
            $table->foreignId('model_id')->nullable()->constrained('vehicle_models')->cascadeOnDelete();
            $table->foreignId('generation_id')->nullable()->constrained('vehicle_generations')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['make_id', 'model_id']);

            // A guide covering "Duster" and "Duster" twice adds nothing; NULLs are distinct in a
            // unique index, so the narrower scopes are guarded by the expression index below.
            $table->unique(['article_id', 'make_id', 'model_id', 'generation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_vehicles');
        Schema::dropIfExists('article_blocks');
    }
};
