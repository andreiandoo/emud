<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store-wide settings as key/value rows.
 *
 * A column per setting would mean a migration every time the owner needs one more field, and
 * these are exactly the values that keep growing. The group is stored so the settings screen
 * can be split into tabs without the UI having to hold a list of which key belongs where.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('group', 32)->index();
            $table->string('key')->unique();

            // jsonb rather than text: some settings are lists (social links, opening hours) and
            // encoding them by hand in a text column invites two different formats over time.
            $table->jsonb('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
