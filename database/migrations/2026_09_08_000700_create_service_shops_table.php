<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Workshops listed in the public directory.
 *
 * Placement is sold, so promotion is an explicit, dated, audited field rather than something
 * implied by ordering. EU consumer law requires paid ranking to be disclosed to the reader, and
 * a paid position that cannot be told apart from an editorial one is both a legal exposure and
 * a trust problem — so the tier is stored, shown, and has an end date that expires on its own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_shops', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            $table->string('county', 64)->index();
            $table->string('city', 96)->index();
            $table->string('address')->nullable();
            $table->string('postal_code', 16)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();

            // Specialities as a list so a workshop can be found by what it actually does, which
            // is how an owner searches, rather than by a single category.
            $table->jsonb('specialities')->nullable();
            $table->boolean('fits_parts_bought_here')->default(false)->index();

            $table->string('status', 24)->default('draft')->index();

            // Paid placement, kept separate from editorial merit and always disclosed.
            $table->string('promotion_tier', 24)->default('none')->index();
            $table->date('promoted_until')->nullable();
            $table->text('promotion_notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['county', 'city']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_shops');
    }
};
