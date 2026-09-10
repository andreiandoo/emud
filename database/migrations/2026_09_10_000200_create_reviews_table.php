<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer reviews, entered in the back office.
 *
 * Nothing here is submitted by the public: reviews arrive by e-mail, on Instagram, in a photo a
 * customer sends after fitting a lift kit, and an operator transcribes them. That is why the
 * author's social handles are columns — the proof that the person is real is the link to their
 * account, and a review that shows a build photo next to the handle that posted it is worth more
 * than a star rating from an anonymous account.
 *
 * It also means every published review is attributable to someone the shop dealt with, which is
 * what the EU rules on review transparency ask a trader to be able to say.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table): void {
            $table->id();

            // A review may be about a product, about a car, or about the shop in general, so
            // every link is optional and the storefront picks the reviews it can place.
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vehicle_collection_id')->nullable()->constrained('vehicle_collections')->nullOnDelete();
            $table->foreignId('make_id')->nullable()->constrained('vehicle_makes')->nullOnDelete();
            $table->foreignId('model_id')->nullable()->constrained('vehicle_models')->nullOnDelete();
            // What the reviewer actually drives, in their words, for the cars the graph does not
            // carry — a re-bodied Land Rover is still a Land Rover to the person who owns it.
            $table->string('vehicle_label')->nullable();

            $table->string('reviewer_name');
            $table->string('reviewer_location')->nullable();
            $table->string('reviewer_instagram')->nullable();
            $table->string('reviewer_facebook')->nullable();

            $table->string('title')->nullable();
            $table->text('body');
            $table->unsignedTinyInteger('rating')->nullable();

            $table->string('image_disk')->default('public');
            $table->string('image_path')->nullable();
            $table->string('video_url')->nullable();

            $table->string('status', 16)->default('draft')->index();
            $table->boolean('is_featured')->default(false)->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'status']);
            $table->index(['vehicle_collection_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
