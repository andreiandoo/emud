<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Collections: the catalogue seen the way a customer sees it — by the car in their driveway.
 *
 * The vehicle graph already knows every make, model and generation, but it is reference data:
 * thirteen thousand makes, no pictures, no words, nothing to put on a page. A collection is the
 * editorial layer over one slice of it — the photo, the video, the copy and the metadata that
 * turn "Suzuki Jimny III" into something worth linking to.
 *
 * Named vehicle_collections rather than collections because the model would otherwise have to be
 * aliased in every file that also touches Illuminate\Support\Collection, which is most of them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_collections', function (Blueprint $table): void {
            $table->id();

            // All three are optional and independent. A collection can be as broad as a make
            // ("Toyota") or as narrow as one generation, and the narrowest one it names is what
            // product matching keys on.
            $table->foreignId('make_id')->nullable()->constrained('vehicle_makes')->nullOnDelete();
            $table->foreignId('model_id')->nullable()->constrained('vehicle_models')->nullOnDelete();
            $table->foreignId('generation_id')->nullable()->constrained('vehicle_generations')->nullOnDelete();

            $table->string('name');
            $table->string('slug')->unique();
            $table->string('subtitle')->nullable();
            $table->text('description')->nullable();

            // Three images because they are cropped for three different jobs and one file cannot
            // serve all of them: a square tile in the carousel, a wide banner on the collection
            // page, and a small badge next to a car in the customer's garage.
            $table->string('square_image_path')->nullable();
            $table->string('wide_image_path')->nullable();
            $table->string('garage_image_path')->nullable();

            $table->string('video_url')->nullable();
            $table->string('video_poster_path')->nullable();

            $table->unsignedSmallInteger('year_from')->nullable();
            $table->unsignedSmallInteger('year_to')->nullable();

            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true)->index();
            // The carousel on the home page shows only these; a shop with four hundred
            // collections cannot put all of them in front of a visitor.
            $table->boolean('is_featured')->default(false)->index();

            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->string('og_image_path')->nullable();
            $table->boolean('robots_index')->default(true);
            $table->boolean('robots_follow')->default(true);

            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['make_id', 'model_id']);
            $table->index(['is_active', 'position']);
        });

        // is_automatic separates what the importer decided from what an operator decided, so a
        // re-import can rebuild its own links without destroying a hand-made one.
        Schema::create('product_vehicle_collection', function (Blueprint $table): void {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_collection_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_automatic')->default(true);
            $table->timestamps();
            $table->primary(['product_id', 'vehicle_collection_id']);
            $table->index('vehicle_collection_id');
        });

        Schema::table('customer_vehicles', function (Blueprint $table): void {
            $table->foreignId('vehicle_collection_id')->nullable()->after('generation_id')
                ->constrained('vehicle_collections')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customer_vehicles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('vehicle_collection_id');
        });

        Schema::dropIfExists('product_vehicle_collection');
        Schema::dropIfExists('vehicle_collections');
    }
};
