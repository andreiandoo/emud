<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_makes', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('vehicle_models', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('make_id')->constrained('vehicle_makes')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['make_id', 'slug']);
        });

        Schema::create('vehicle_generations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('model_id')->constrained('vehicle_models')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('year_from');
            $table->unsignedSmallInteger('year_to')->nullable();
            $table->string('chassis_code')->nullable()->index();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->index(['model_id', 'year_from', 'year_to']);
        });

        Schema::create('vehicle_engines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('generation_id')->constrained('vehicle_generations')->cascadeOnDelete();
            $table->string('name');
            $table->string('engine_code')->nullable()->index();
            $table->decimal('displacement_l', 4, 2)->nullable();
            $table->string('fuel_type', 24)->nullable();
            $table->unsignedSmallInteger('power_hp')->nullable();
            $table->timestamps();
        });

        Schema::create('vehicle_configurations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('generation_id')->constrained('vehicle_generations')->cascadeOnDelete();
            $table->foreignId('engine_id')->nullable()->constrained('vehicle_engines')->nullOnDelete();
            $table->unsignedSmallInteger('year');
            $table->string('body_type', 32)->nullable();
            $table->string('drive_type', 16)->default('4x4');
            $table->string('transmission', 32)->nullable();
            $table->unsignedSmallInteger('doors')->nullable();
            $table->string('wheelbase', 32)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->index(['generation_id', 'year']);
        });

        Schema::create('product_fitments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('make_id')->nullable()->constrained('vehicle_makes')->cascadeOnDelete();
            $table->foreignId('model_id')->nullable()->constrained('vehicle_models')->cascadeOnDelete();
            $table->foreignId('generation_id')->nullable()->constrained('vehicle_generations')->cascadeOnDelete();
            $table->foreignId('engine_id')->nullable()->constrained('vehicle_engines')->cascadeOnDelete();
            $table->unsignedSmallInteger('year_from')->nullable();
            $table->unsignedSmallInteger('year_to')->nullable();
            $table->string('body_type', 32)->nullable();
            $table->string('position', 32)->nullable();
            $table->boolean('requires_modification')->default(false);
            $table->text('notes')->nullable();
            $table->jsonb('constraints')->nullable();
            $table->string('source')->nullable();
            $table->timestamps();
            $table->index(['make_id', 'model_id', 'generation_id']);
            $table->index(['product_id', 'variant_id']);
        });

        Schema::create('customer_vehicles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('configuration_id')->nullable()->constrained('vehicle_configurations')->nullOnDelete();
            $table->foreignId('make_id')->constrained('vehicle_makes')->restrictOnDelete();
            $table->foreignId('model_id')->constrained('vehicle_models')->restrictOnDelete();
            $table->foreignId('generation_id')->nullable()->constrained('vehicle_generations')->nullOnDelete();
            $table->string('nickname')->nullable();
            $table->unsignedSmallInteger('year');
            $table->string('vin', 17)->nullable();
            $table->string('registration_number', 16)->nullable();
            $table->jsonb('modifications')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->index(['user_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_vehicles');
        Schema::dropIfExists('product_fitments');
        Schema::dropIfExists('vehicle_configurations');
        Schema::dropIfExists('vehicle_engines');
        Schema::dropIfExists('vehicle_generations');
        Schema::dropIfExists('vehicle_models');
        Schema::dropIfExists('vehicle_makes');
    }
};
